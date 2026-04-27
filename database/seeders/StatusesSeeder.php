<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusesSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = ['Active', 'Disabled'];

        foreach ($statuses as $status) {
            Status::updateOrCreate(['name' => $status]);
        }
    }
}
