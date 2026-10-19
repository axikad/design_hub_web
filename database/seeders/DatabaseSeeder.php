<?php

namespace Database\Seeders;

use App\Models\Role;
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

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@designhub.local'],
            [
                'name' => 'DesignHub Admin',
                'password' => 'password',
            ],
        );

        $admin->update([
            'role_id' => Role::query()->where('slug', 'super_admin')->value('id'),
        ]);
    }
}
