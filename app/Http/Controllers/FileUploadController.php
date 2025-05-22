<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChunkUploadRequest;
use App\Http\Resources\UserUploadsResource;
use App\Models\FileUpload;
use App\Services\FileUploadService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileUploadController extends Controller
{

    public function __construct(private readonly FileUploadService $uploadService)
    {
    }

    public function initUpload(): JsonResponse
    {
        $uploadId = (string) Str::uuid();
        return response()->json(['upload_id' => $uploadId]);
    }

    public function uploadChunk(ChunkUploadRequest $request): JsonResponse
    {
        $uploadId = $request->input('upload_id');
        $chunkNumber = (int) $request->input('chunk_number');
        $totalChunks = (int) $request->input('total_chunks');

        try {
            $fileUpload = FileUpload::updateOrCreate(
                ['upload_id' => $uploadId],
                [
                    'user_id' => auth()->id(),
                    'original_filename' => $request->input('filename'),
                    'mime_type' => $request->input('mime_type'),
                    'total_size' => $request->input('total_size'),
                    'total_chunks' => $totalChunks,
                    'status' => 'pending',
                ]
            );

            if (!$this->uploadService
                ->storeChunk($uploadId, $chunkNumber, $request->file('chunk'))
            ) {

                return response()
                    ->json(['error' => 'Failed to store chunk'], 500);
            }


            if ($this->uploadService->isUploadComplete($fileUpload)) {
                try {
                    $this->uploadService->finalizeUpload($fileUpload);
                } catch (Exception $e) {
                    Log::error('Finalization failed', [
                        'upload_id' => $uploadId,
                        'error' => $e->getMessage(),
                    ]);
                    $fileUpload->status = 'failed';
                    $fileUpload->save();
                }
            }

            return response()->json([
                'status' => $fileUpload->status,
                'is_complete' => $this->uploadService->isUploadComplete($fileUpload),
                'total_chunks' => $fileUpload->total_chunks,
                'filename' => $fileUpload->original_filename,
            ]);

        } catch (Exception $e) {
            Log::error('Error processing chunk upload', [
                'upload_id' => $uploadId,
                'chunk' => $chunkNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete(string $uploadId): JsonResponse
    {
        $upload = FileUpload::where('upload_id', $uploadId)->firstOrFail();
        $this->uploadService->deleteUpload($upload);
        return response()
            ->json(['message' => 'Upload deleted successfully']);
    }

    public function uploadStatus(string $uploadId): JsonResponse
    {
        $fileUpload = FileUpload::where('upload_id', $uploadId)->firstOrFail();

        return response()->json([
            'status' => $fileUpload->status,
            'is_complete' => $this->uploadService->isUploadComplete($fileUpload),
            'total_chunks' => $fileUpload->total_chunks,
            'filename' => $fileUpload->original_filename,
        ]);
    }

    public function download(string $uploadId): StreamedResponse
    {
        $upload = FileUpload::where('upload_id', $uploadId)->firstOrFail();

        if ($upload->status !== 'completed') {
            abort(404, 'The File is not ready for download');
        }

        return Storage::disk('local')->download(
            $upload->storage_path,
            $upload->original_filename,
            ['Content-Type' => $upload->mime_type]
        );
    }

    public function getUserUploads(): JsonResponse
    {
        $uploads = FileUpload::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(UserUploadsResource::collection($uploads));
    }
}
