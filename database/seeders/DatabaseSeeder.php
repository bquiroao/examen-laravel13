<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RbacCatalogSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoNetworkSeeder::class);
        }
    }
}
