<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('content');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();

        DB::table('content_pages')->insert([
            [
                'slug' => 'terms-and-conditions',
                'title' => 'Terms and Conditions',
                'content' => 'Welcome to Textile Design Hub. Please update these terms from the admin panel before publishing the mobile application.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'content' => 'This page explains how Textile Design Hub handles user information. Please replace this draft with your approved privacy policy.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'refund-policy',
                'title' => 'Refund Policy',
                'content' => 'Please add your approved purchase, cancellation, and refund terms from the admin panel.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'about-app',
                'title' => 'About Textile Design Hub',
                'content' => 'Textile Design Hub is a marketplace for discovering and purchasing machine-ready design files.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
