<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->foreignId('design_file_type_id')
                ->nullable()
                ->after('design_type_id')
                ->constrained('design_file_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('design_file_type_id');
        });
    }
};
