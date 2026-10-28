<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_variants', function (Blueprint $table) {
            $table->decimal('sequence_type', 12, 2)->default(0)->after('needle');
        });
    }

    public function down(): void
    {
        Schema::table('design_variants', function (Blueprint $table) {
            $table->dropColumn('sequence_type');
        });
    }
};
