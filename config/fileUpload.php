<?php

return [
    'max_file_size' => env('MAX_UPLOAD_FILE_SIZE', 1073741824), // 1GB
    'max_chunk_size' => env('MAX_UPLOAD_CHUNK_SIZE', 10240), // 10MB
    'allowed_mime_types' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
        'image/gif',
        'video/mp4',
        'application/zip',
    ],
];
