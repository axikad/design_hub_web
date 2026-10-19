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
        $this->call(DesignFileTypeSeeder::class);

        User::query()->updateOrCreate(
            ['email' => 'admin@designhub.local'],
            [
                'name' => 'DesignHub Admin',
                'password' => 'password',
                'is_admin' => true,
            ],
        );
    }
}
