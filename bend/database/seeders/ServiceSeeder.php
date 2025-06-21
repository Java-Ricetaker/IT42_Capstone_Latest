<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Service::insert([
            [
                'name' => 'Dental Cleaning',
                'description' => 'Basic oral prophylaxis procedure',
                'price' => 2500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tooth Extraction',
                'description' => 'Simple or surgical removal of tooth',
                'price' => 3000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tooth Filling',
                'description' => 'Resin composite filling for cavities',
                'price' => 2000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
