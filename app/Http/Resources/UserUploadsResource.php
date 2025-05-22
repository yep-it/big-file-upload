<?php

namespace App\Http\Resources;

use App\Models\FileUpload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FileUpload */
class UserUploadsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'upload_id' => $this->upload_id,
            'filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'total_size' => $this->total_size,
            'total_chunks' => $this->total_chunks,
            'uploaded_chunks' => $this->uploaded_chunks,
            'storage_path' => $this->storage_path,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
