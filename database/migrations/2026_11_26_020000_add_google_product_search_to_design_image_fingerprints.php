<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_image_fingerprints', function (Blueprint $table) {
            $table->string('google_product_id')->nullable()->after('vector_error');
            $table->string('google_reference_image_id')->nullable()->after('google_product_id');
            $table->string('google_gcs_uri')->nullable()->after('google_reference_image_id');
            $table->string('google_status', 20)->default('disabled')->after('google_gcs_uri');
            $table->text('google_error')->nullable()->after('google_status');
            $table->index(['google_status', 'design_id']);
        });
    }

    public function down(): void
    {
        Schema::table('design_image_fingerprints', function (Blueprint $table) {
            $table->dropIndex(['google_status', 'design_id']);
            $table->dropColumn([
                'google_product_id',
                'google_reference_image_id',
                'google_gcs_uri',
                'google_status',
                'google_error',
            ]);
        });
    }
};
