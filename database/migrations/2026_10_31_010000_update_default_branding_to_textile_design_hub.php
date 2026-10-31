<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_settings')
            ->where('name', 'DesignHub Admin')
            ->update(['name' => 'Textile Design Hub']);

        DB::table('content_pages')
            ->where('title', 'About DesignHub')
            ->where('content', 'DesignHub is a marketplace for discovering and purchasing machine-ready design files.')
            ->update([
                'title' => 'About Textile Design Hub',
                'content' => 'Textile Design Hub is a marketplace for discovering and purchasing machine-ready design files.',
            ]);

        DB::table('content_pages')
            ->where('content', 'Welcome to DesignHub. Please update these terms from the admin panel before publishing the mobile application.')
            ->update([
                'content' => 'Welcome to Textile Design Hub. Please update these terms from the admin panel before publishing the mobile application.',
            ]);

        DB::table('content_pages')
            ->where('content', 'This page explains how DesignHub handles user information. Please replace this draft with your approved privacy policy.')
            ->update([
                'content' => 'This page explains how Textile Design Hub handles user information. Please replace this draft with your approved privacy policy.',
            ]);

        Cache::forget('site_settings.current');
    }

    public function down(): void
    {
        DB::table('site_settings')
            ->where('name', 'Textile Design Hub')
            ->update(['name' => 'DesignHub Admin']);

        Cache::forget('site_settings.current');
    }
};
