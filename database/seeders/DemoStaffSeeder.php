<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Database\Seeders\Concerns\SeedsUsers;
use Illuminate\Database\Seeder;

class DemoStaffSeeder extends Seeder
{
    use SeedsUsers;

    public function run(): void
    {
        $this->ensureDemoStaff(UserRole::Sales, 'sale', 'Telesale', 55);
        $this->ensureDemoStaff(UserRole::Marketing, 'mkt', 'Marketing', 55);
    }
}
