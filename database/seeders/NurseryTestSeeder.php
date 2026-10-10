<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class NurseryTestSeeder extends Seeder
{
    public function run(): void
    {
        $tenantA = Tenant::firstOrCreate([
            'name' => 'Nursery Group A',
        ]);

        $tenantB = Tenant::firstOrCreate([
            'name' => 'Nursery Group B',
        ]);

        $roomA = $tenantA->rooms()->firstOrCreate([
            'name' => 'Sunflowers',
        ]);

        $roomB = $tenantB->rooms()->firstOrCreate([
            'name' => 'Little Explorers',
        ]);

        $childA = $tenantA->children()->firstOrCreate([
            'name' => 'Child A',
        ]);

        $childB = $tenantB->children()->firstOrCreate([
            'name' => 'Child B',
        ]);

        $this->command->info('Tenant A ID: ' . $tenantA->id);
        $this->command->info('Tenant B ID: ' . $tenantB->id);
        $this->command->info('Room A ID: ' . $roomA->id);
        $this->command->info('Room B ID: ' . $roomB->id);
        $this->command->info('Child A ID: ' . $childA->id);
        $this->command->info('Child B ID: ' . $childB->id);
    }
}