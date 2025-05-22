<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileUpload extends Model
{
    protected $fillable = [
        'user_id',
        'upload_id',
        'original_filename',
        'mime_type',
        'total_size',
        'total_chunks',
        'storage_path',
        'status',
    ];

    protected $casts = [
        'total_size' => 'integer',
        'total_chunks' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


}
