<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_settings')
            ->where('duplicate_image_similarity', 80)
            ->update(['duplicate_image_similarity' => 95]);
    }

    public function down(): void
    {
        DB::table('site_settings')
            ->where('duplicate_image_similarity', 95)
            ->update(['duplicate_image_similarity' => 80]);
    }
};
