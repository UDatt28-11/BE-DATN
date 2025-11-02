<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_images', function (Blueprint $table) {
            $table->string('drive_file_id')->nullable()->after('room_id');
            $table->string('web_view_link')->nullable()->after('drive_file_id');
            $table->string('mime_type')->nullable()->after('web_view_link');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('room_images', function (Blueprint $table) {
            $table->dropColumn(['drive_file_id', 'web_view_link', 'mime_type', 'size_bytes']);
        });
    }
};


