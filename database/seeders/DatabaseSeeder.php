<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            PublicContentSeeder::class,
            AcademicDefaultsSeeder::class,
            DesignRefreshSeeder::class,
        ]);

        if (app()->environment('production') && filter_var(env('SCHOOL_DEMO_DATA_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(DemoReportCardSeeder::class);
        }
    }
}
