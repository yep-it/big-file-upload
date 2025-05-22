<script setup lang="ts">
import { useFileUpload } from '@/composables/useFileUpload';
import { onMounted, ref } from 'vue';

const acceptedTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg',
    'image/png',
    'image/gif',
    'video/mp4',
    'application/zip',
];

const fileInput = ref(null);
const showDeleteModal = ref(false);
const uploadToDelete = ref(null);

const { initializeUpload, uploadFile, resumeUpload, removeUpload, getUploadProgress, activeUploads, loadExistingUploads } = useFileUpload();

onMounted(() => {
    loadExistingUploads();
});

const triggerFileInput = () => {
    fileInput.value?.click();
};

const handleFileSelect = async (event) => {
    const input = event.target;
    if (input.files && input.files.length > 0) {
        await startUpload(input.files[0]);
        input.value = ''; // Reset input after upload starts
    }
};

const handleDrop = async (event) => {
    const files = event.dataTransfer?.files;
    if (files && files.length > 0) {
        await startUpload(files[0]);
    }
};

const startUpload = async (file) => {
    // Validate file size (1GB max)
    if (file.size > 1024 * 1024 * 1024) {
        alert('File size exceeds 1GB limit');
        return;
    }

    // Validate file type
    if (!acceptedTypes.includes(file.type)) {
        alert('File type not supported');
        return;
    }

    try {
        const uploadId = await initializeUpload(file);
        await uploadFile(uploadId);
    } catch (error) {
        console.error('Upload failed:', error);
    }
};

const confirmDelete = (uploadId) => {
    uploadToDelete.value = uploadId;
    showDeleteModal.value = true;
};

const cancelDelete = () => {
    showDeleteModal.value = false;
    uploadToDelete.value = null;
};

const deleteUpload = async () => {
    if (!uploadToDelete.value) return;

    try {
        await removeUpload(uploadToDelete.value);
    } finally {
        showDeleteModal.value = false;
        uploadToDelete.value = null;
    }
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>

<template>
    <div class="mx-auto max-w-2xl p-4">
        <div
            class="rounded-lg border-2 border-dashed border-gray-300 p-8 text-center transition-colors hover:border-gray-400"
            @dragover.prevent
            @drop.prevent="handleDrop"
            @click="triggerFileInput"
        >
            <input type="file" ref="fileInput" class="hidden" @change="handleFileSelect" :accept="acceptedTypes.join(',')" />
            <div class="text-gray-600">
                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                    <path
                        d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
                <p class="mt-1">Drag and drop a file here, or click to select</p>
                <p class="mt-1 text-sm text-gray-500">Maximum file size: 1GB</p>
            </div>
        </div>

        <!-- Active Uploads -->
        <div class="mt-6 space-y-4">
            <div v-for="[uploadId, upload] in Array.from(activeUploads)" :key="uploadId" class="rounded-lg bg-white p-4 shadow">
                <div class="mb-2 flex items-center justify-between">
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-sm font-medium text-gray-900">
                            {{ upload.filename }}
                        </h3>
                        <p class="text-xs text-gray-500">
                            {{ formatFileSize(upload.totalSize) }}
                        </p>
                    </div>
                    <div class="ml-4 flex items-center space-x-2">
                        <a
                            v-if="upload.status === 'completed'"
                            :href="`/upload/${uploadId}/download`"
                            class="inline-flex items-center rounded-md bg-blue-100 px-3 py-1 text-sm font-medium text-blue-700 hover:bg-blue-200"
                            target="_blank"
                        >
                            <span class="mr-1">Download</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                />
                            </svg>
                        </a>
                        <button
                            v-if="upload.status === 'failed'"
                            @click="resumeUpload(uploadId)"
                            class="inline-flex items-center rounded-md bg-green-100 px-3 py-1 text-sm font-medium text-green-700 hover:bg-green-200"
                        >
                            <span class="mr-1">Retry</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                        </button>
                        <button
                            @click="confirmDelete(uploadId)"
                            class="inline-flex items-center rounded-md bg-red-100 px-3 py-1 text-sm font-medium text-red-700 hover:bg-red-200"
                        >
                            <span class="mr-1">Remove</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="h-2.5 w-full rounded-full bg-gray-200">
                    <div
                        class="h-2.5 rounded-full transition-all duration-300"
                        :class="{
                            'bg-blue-600': upload.status === 'uploading' || upload.status === 'pending',
                            'bg-green-600': upload.status === 'completed',
                            'bg-red-600': upload.status === 'failed',
                        }"
                        :style="{ width: `${getUploadProgress(uploadId)}%` }"
                    ></div>
                </div>

                <!-- Status -->
                <div class="mt-2 flex justify-between text-sm">
                    <span
                        :class="{
                            'text-blue-600': upload.status === 'uploading' || upload.status === 'pending',
                            'text-green-600': upload.status === 'completed',
                            'text-red-600': upload.status === 'failed',
                        }"
                    >
                        {{ upload.status.charAt(0).toUpperCase() + upload.status.slice(1) }}
                    </span>
                    <span class="text-gray-500" v-if="getUploadProgress(uploadId)"> {{ Math.round(getUploadProgress(uploadId)) }}% </span>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="bg-opacity-75 fixed inset-0 z-50 flex items-center justify-center bg-gray-500">
                <div class="mx-4 max-w-sm rounded-lg bg-white p-6" @click.stop>
                    <h3 class="mb-4 text-lg font-medium text-gray-900">Confirm Deletion</h3>
                    <p class="mb-6 text-gray-500">Are you sure you want to delete this file? This action cannot be undone.</p>
                    <div class="flex justify-end space-x-4">
                        <button @click="cancelDelete" class="rounded-md bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200">Cancel</button>
                        <button @click="deleteUpload" class="rounded-md bg-red-600 px-4 py-2 text-white hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
