<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folder_files', function (Blueprint $table) {
            $table->string('public_id')->nullable()->unique()->after('id');
        });

        DB::table('folder_files')->orderBy('id')->each(function (object $file): void {
            DB::table('folder_files')->where('id', $file->id)->update([
                'public_id' => 'folder_file_'.Str::lower(Str::random(12)),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('folder_files', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
