<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // Existing permissions remain usable until their CRUD flags are
            // migrated to individual permission records and permission_role.
            $table->string('action', 20)->default('legacy')->after('slug');
            $table->text('description')->nullable()->after('action');
            $table->boolean('is_active')->default(true)->index()->after('description');
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();

            $table->index(['module_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['module_id', 'action']);
            $table->dropIndex(['is_active']);
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['action', 'description', 'is_active']);
        });
    }
};
