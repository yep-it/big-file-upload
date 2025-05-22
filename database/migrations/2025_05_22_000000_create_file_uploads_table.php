<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('upload_id')->unique();
            $table->string('original_filename');
            $table->string('mime_type');
            $table->bigInteger('total_size');
            $table->integer('total_chunks');
            $table->string('storage_path')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_uploads');
    }
};
