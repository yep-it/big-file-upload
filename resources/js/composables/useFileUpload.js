import axios from 'axios';
import { computed, onBeforeUnmount, ref } from 'vue';

const CHUNK_SIZE = 1024 * 1024 * 2; // 2MB chunks
const MAX_RETRIES = 3;
const MAX_CONCURRENT_CHUNKS = 3;
const STATUS_CHECK_INTERVAL = 3000; // 3 seconds
const PROGRESS_TIMEOUT = 10000; // 10 seconds

export function useFileUpload() {
    const activeUploads = ref(new Map());
    const statusCheckTimers = new Map();

    const stopStatusCheck = (uploadId) => {
        const timerId = statusCheckTimers.get(uploadId);
        if (timerId) {
            clearInterval(timerId);
            statusCheckTimers.delete(uploadId);
        }
    };

    const checkUploadStatus = async (uploadId) => {
        const session = activeUploads.value.get(uploadId);
        if (!session) return;

        try {
            const response = await axios.get(`/upload/${uploadId}/status`);

            if (response.data.status === 'completed') {
                session.status = 'completed';
                stopStatusCheck(uploadId);
                return;
            }

            if (response.data.status === 'failed') {
                session.status = 'failed';
                stopStatusCheck(uploadId);
                return;
            }

            const currentTime = Date.now();
            const noProgressTimeout = currentTime - session.lastProgressUpdate > PROGRESS_TIMEOUT;

            if (response.data.missing_chunks?.length > 0 && noProgressTimeout) {
                // Only retry if we haven't seen progress for a while
                session.missingChunks = response.data.missing_chunks;
                await retryMissingChunks(uploadId);
                session.lastProgressUpdate = currentTime;
            }
        } catch (error) {
            console.error('Failed to check upload status:', error);
        }
    };

    const startStatusCheck = (uploadId) => {
        stopStatusCheck(uploadId); // Clear any existing timer
        statusCheckTimers.set(
            uploadId,
            window.setInterval(() => checkUploadStatus(uploadId), STATUS_CHECK_INTERVAL),
        );
    };

    const addUploadToBeginning = (uploadId, session) => {
        const newMap = new Map();
        newMap.set(uploadId, session);
        activeUploads.value.forEach((value, key) => {
            if (key !== uploadId) {
                newMap.set(key, value);
            }
        });
        activeUploads.value = newMap;
    };

    const initializeUpload = async (file) => {
        const response = await axios.post(route('upload.init'));
        const uploadId = response.data.upload_id;

        // Create chunks
        const chunks = [];
        let offset = 0;
        let chunkId = 0;
        while (offset < file.size) {
            const chunk = file.slice(offset, offset + CHUNK_SIZE);
            chunks.push({
                id: chunkId,
                file: chunk,
                status: 'pending',
                attempts: 0,
                progress: 0,
            });
            offset += CHUNK_SIZE;
            chunkId++;
        }

        const session = {
            uploadId,
            filename: file.name,
            mimeType: file.type,
            totalSize: file.size,
            chunks,
            status: 'pending',
            missingChunks: [],
            lastProgressUpdate: Date.now(),
        };

        addUploadToBeginning(uploadId, session);
        return uploadId;
    };

    const uploadChunk = async (uploadId, chunk) => {
        const session = activeUploads.value.get(uploadId);
        if (!session) return false;

        try {
            chunk.status = 'uploading';
            const formData = new FormData();
            formData.append('upload_id', uploadId);
            formData.append('chunk', chunk.file);
            formData.append('chunk_number', chunk.id.toString());
            formData.append('total_chunks', session.chunks.length.toString());
            formData.append('total_size', session.totalSize.toString());
            formData.append('filename', session.filename);
            formData.append('mime_type', session.mimeType);

            const response = await axios.post(route('upload.chunk'), formData, {
                onUploadProgress: (progressEvent) => {
                    if (progressEvent.total) {
                        chunk.progress = (progressEvent.loaded / progressEvent.total) * 100;
                    }
                },
            });

            chunk.status = 'completed';
            chunk.progress = 100;
            session.lastProgressUpdate = Date.now();

            // Update missing chunks from response
            if (response.data.missing_chunks) {
                session.missingChunks = response.data.missing_chunks;
            }

            return true;
        } catch (error) {
            chunk.status = 'failed';
            chunk.attempts++;
            console.error(`Chunk ${chunk.id} failed:`, error);

            if (chunk.attempts < MAX_RETRIES) {
                // Add exponential backoff
                const delay = Math.min(1000 * Math.pow(2, chunk.attempts), 30000);
                await new Promise((resolve) => setTimeout(resolve, delay));
                return uploadChunk(uploadId, chunk);
            }
            return false;
        }
    };

    const uploadChunkBatch = async (uploadId, chunks) => {
        await Promise.all(chunks.map((chunk) => uploadChunk(uploadId, chunk)));
    };

    const retryMissingChunks = async (uploadId) => {
        const session = activeUploads.value.get(uploadId);
        if (!session || !session.missingChunks.length) return;

        // Reset missing chunks that failed
        const chunksToRetry = session.chunks.filter(
            (chunk) => session.missingChunks.includes(chunk.id) && chunk.attempts < MAX_RETRIES && chunk.status !== 'completed',
        );

        if (chunksToRetry.length === 0) return;

        // Reset chunk status for retry
        chunksToRetry.forEach((chunk) => {
            chunk.status = 'pending';
            chunk.progress = 0;
        });

        // Upload in smaller batches
        for (let i = 0; i < chunksToRetry.length; i += MAX_CONCURRENT_CHUNKS) {
            const batch = chunksToRetry.slice(i, i + MAX_CONCURRENT_CHUNKS);
            await uploadChunkBatch(uploadId, batch);
        }
    };

    const uploadFile = async (uploadId) => {
        const session = activeUploads.value.get(uploadId);
        if (!session) return;

        try {
            session.status = 'uploading';
            session.lastProgressUpdate = Date.now();
            startStatusCheck(uploadId);

            // Upload initial chunks in batches
            const chunks = [...session.chunks];
            for (let i = 0; i < chunks.length; i += MAX_CONCURRENT_CHUNKS) {
                const batch = chunks.slice(i, i + MAX_CONCURRENT_CHUNKS);
                await uploadChunkBatch(uploadId, batch);
            }
        } catch (error) {
            console.error('Upload failed:', error);
            session.status = 'failed';
            stopStatusCheck(uploadId);
        }
    };

    const resumeUpload = async (uploadId) => {
        const session = activeUploads.value.get(uploadId);
        if (!session || session.status === 'completed') return;

        session.lastProgressUpdate = Date.now();

        // Reset failed chunks
        session.chunks.forEach((chunk) => {
            if (chunk.status === 'failed' || chunk.status === 'pending') {
                chunk.attempts = 0;
                chunk.progress = 0;
                chunk.status = 'pending';
            }
        });

        return uploadFile(uploadId);
    };

    const removeUpload = async (uploadId) => {
        try {
            await axios.delete(`/upload/${uploadId}`);
        } catch (error) {
            console.error('Failed to delete upload:', error);
        }
        stopStatusCheck(uploadId);
        activeUploads.value.delete(uploadId);
    };

    const getUploadProgress = (uploadId) => {
        const session = activeUploads.value.get(uploadId);
        if (!session) return 0;

        const completedChunks = session.chunks.filter((chunk) => chunk.status === 'completed');
        return Math.round((completedChunks.length / session.chunks.length) * 100);
    };

    const loadExistingUploads = async () => {
        try {
            const response = await axios.get(route('upload.uploads'));
            const uploads = response.data;

            const newMap = new Map();
            uploads.forEach((upload) => {
                if (!activeUploads.value.has(upload.upload_id)) {
                    const session = {
                        uploadId: upload.upload_id,
                        filename: upload.filename,
                        mimeType: upload.mime_type,
                        totalSize: upload.total_size,
                        status: upload.status,
                        chunks: [], // Empty chunks since file is already uploaded
                        missingChunks: [],
                        lastProgressUpdate: new Date(upload.created_at).getTime(),
                    };
                    newMap.set(upload.upload_id, session);

                    if (upload.status === 'pending' || upload.status === 'uploading') {
                        startStatusCheck(upload.upload_id);
                    }
                }
            });
            // Add existing uploads after new ones
            activeUploads.value.forEach((value, key) => {
                if (!newMap.has(key)) {
                    newMap.set(key, value);
                }
            });
            activeUploads.value = newMap;
        } catch (error) {
            console.error('Failed to load existing uploads:', error);
        }
    };

    // Clean up on unmount
    onBeforeUnmount(() => {
        for (const uploadId of statusCheckTimers.keys()) {
            stopStatusCheck(uploadId);
        }
    });

    return {
        initializeUpload,
        uploadFile,
        resumeUpload,
        removeUpload,
        getUploadProgress,
        loadExistingUploads,
        activeUploads: computed(() => activeUploads.value),
    };
}
