<?php

use App\Http\Controllers\FileUploadController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::post('/upload/init', [FileUploadController::class, 'initUpload'])->name('upload.init');
    Route::post('/upload/chunk', [FileUploadController::class, 'uploadChunk'])->name('upload.chunk');
    Route::get('/upload/{uploadId}/status', [FileUploadController::class, 'uploadStatus'])->name('upload.status');
    Route::get('/upload/{uploadId}/download', [FileUploadController::class, 'download'])->name('upload.download');
    Route::get('/upload/uploads', [FileUploadController::class, 'getUserUploads'])->name('upload.uploads');
    Route::delete('/upload/{uploadId}', [FileUploadController::class, 'delete'])->name('upload.delete');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
