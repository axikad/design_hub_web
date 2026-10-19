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
        Schema::table('modules', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->text('description')->nullable()->after('slug');
            $table->boolean('is_active')->default(true)->after('description');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            $table->foreignId('created_by')->nullable()->after('sort_order')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        $usedSlugs = [];

        DB::table('modules')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->each(function (object $module) use (&$usedSlugs): void {
                $baseSlug = Str::slug((string) $module->name, '_');
                $baseSlug = $baseSlug !== '' ? $baseSlug : 'module_'.$module->id;
                $slug = $baseSlug;

                if (isset($usedSlugs[$slug])) {
                    $slug .= '_'.$module->id;
                }

                $usedSlugs[$slug] = true;

                DB::table('modules')->where('id', $module->id)->update(['slug' => $slug]);
            });

        Schema::table('modules', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'description', 'is_active', 'sort_order']);
        });
    }
};
