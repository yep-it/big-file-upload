<?php

namespace App\Services;

use App\Exceptions\FileUploadException;
use App\Models\FileUpload;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    public function storeChunk(string $uploadId, int $chunkNumber, UploadedFile $chunk): bool
    {
        if (!$chunk->isValid()) {
            Log::error('Invalid chunk received', [
                'upload_id' => $uploadId,
                'chunk' => $chunkNumber,
            ]);
            return false;
        }

        $chunkDir = storage_path("app/chunks/$uploadId");
        if (!file_exists($chunkDir)) {
            mkdir($chunkDir, 0777, true);
        }

        $success = Storage::disk('local')->putFileAs(
            "chunks/$uploadId",
            $chunk,
            (string) $chunkNumber
        );

        if (!$success) {
            Log::error('Failed to store chunk', [
                'upload_id' => $uploadId,
                'chunk' => $chunkNumber,
            ]);
            return false;
        }

        if (!Storage::disk('local')->exists("chunks/$uploadId/$chunkNumber")) {
            Log::error('Chunk is not found after storage', [
                'upload_id' => $uploadId,
                'chunk' => $chunkNumber,
            ]);
            return false;
        }

        return true;
    }

    public function isUploadComplete(FileUpload $fileUpload): bool
    {
        for ($i = 0; $i < $fileUpload->total_chunks; $i++) {
            $chunkPath = "chunks/$fileUpload->upload_id/$i";
            if (!Storage::disk('local')->exists($chunkPath)) {
                Log::debug('Chunk file missing from storage', [
                    'upload_id' => $fileUpload->upload_id,
                    'chunk' => $i,
                    'path' => $chunkPath,
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function finalizeUpload(FileUpload $fileUpload): void
    {
        try {
            $fileUpload->status = 'processing';
            $fileUpload->save();

            $uploadDir = storage_path("app/uploads/$fileUpload->upload_id");
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $finalPath = "uploads/$fileUpload->upload_id/$fileUpload->original_filename";
            $tmpPath = storage_path("app/tmp/$fileUpload->upload_id");

            if (!file_exists(dirname($tmpPath))) {
                mkdir(dirname($tmpPath), 0777, true);
            }

            $outFile = fopen($tmpPath, 'wb');
            if (!$outFile) {
                throw new FileUploadException("Could not create a temporary file");
            }

            $totalWritten = 0;
            $expectedSize = $fileUpload->total_size;

            for ($i = 0; $i < $fileUpload->total_chunks; $i++) {
                $chunkPath = "chunks/$fileUpload->upload_id/$i";
                $chunkContent = Storage::disk('local')->get($chunkPath);
                $written = fwrite($outFile, $chunkContent);

                if ($written === false) {
                    fclose($outFile);
                    throw new FileUploadException("Failed writing chunk $i");
                }

                $totalWritten += $written;
                Storage::disk('local')->delete($chunkPath);
            }

            fclose($outFile);

            if ($totalWritten !== $expectedSize) {
                throw new FileUploadException(
                    "File size mismatch. Expected: $expectedSize, Got: $totalWritten"
                );
            }

            $success = Storage::disk('local')->putFileAs(
                "uploads/$fileUpload->upload_id",
                $tmpPath,
                $fileUpload->original_filename
            );

            if (!$success) {
                throw new FileUploadException("Failed to move a file to the final location");
            }

            if (!Storage::disk('local')->exists($finalPath)) {
                throw new FileUploadException("The Final file isn't found after the move");
            }

            @unlink($tmpPath);
            Storage::disk('local')->deleteDirectory("chunks/$fileUpload->upload_id");

            $fileUpload->storage_path = $finalPath;
            $fileUpload->status = 'completed';
            $fileUpload->save();

        } catch (Exception $e) {
            Log::error('Failed to finalize upload', [
                'upload_id' => $fileUpload->upload_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $fileUpload->status = 'failed';
            $fileUpload->save();
            throw $e;
        }
    }

    public function deleteUpload(FileUpload $fileUpload): void
    {
        if ($fileUpload->storage_path) {
            Storage::disk('local')->delete($fileUpload->storage_path);
        }

        Storage::disk('local')->deleteDirectory("chunks/$fileUpload->upload_id");

        $fileUpload->delete();
    }
}
