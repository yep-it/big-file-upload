<script setup lang="ts">
import FileUploader from '@/components/FileUploader.vue';
</script>
<template>
    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h2 class="mb-6 text-2xl font-semibold">File Upload</h2>
                    <FileUploader />
                </div>
            </div>
        </div>
    </div>
</template>
