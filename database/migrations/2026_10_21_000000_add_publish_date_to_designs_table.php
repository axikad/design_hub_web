<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->date('publish_date')->nullable()->after('show_price')->index();
        });

        DB::table('designs')
            ->whereNull('publish_date')
            ->update(['publish_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->dropColumn('publish_date');
        });
    }
};
