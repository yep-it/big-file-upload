<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChunkUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'upload_id' => 'required|string',
            'chunk' => 'required|file|max:' . config('fileUpload.max_chunk_size'),
            'chunk_number' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
            'total_size' => 'required|integer|min:1|max:' . config('fileUpload.max_file_size'),
            'filename' => 'required|string',
            'mime_type' => 'required|string|in:' . implode(',', config('fileUpload.allowed_mime_types')),
        ];
    }
}
