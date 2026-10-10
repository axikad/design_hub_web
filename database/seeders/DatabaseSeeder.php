<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);
        $this->call(SiteSettingSeeder::class);
        $this->call(DesignCatalogSeeder::class);
        $this->call(DesignFormOptionSeeder::class);

        User::factory()->create([
            'name' => 'DesignHub Admin',
            'email' => 'admin@designhub.local',
            'password' => 'password',
            'is_admin' => true,
        ]);
    }
}
