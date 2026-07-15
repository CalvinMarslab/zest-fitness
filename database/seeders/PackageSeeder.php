<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name'        => 'Trial',
                'description' => 'Try us out — 2 classes over 14 days. One-time only.',
                'credits'     => 2,
                'period_days' => 14,
                'price'       => 60,
                'badge'       => 'Try Us',
                'is_active'   => true,
                'is_trial'    => true,
                'sort_order'  => 0,
            ],
            [
                'name'        => 'Limited 1-Month',
                'description' => 'Great for those starting out. 2 classes per week, no lock-in.',
                'credits'     => 8,
                'period_days' => 30,
                'price'       => 200,
                'badge'       => 'Limited',
                'is_active'   => true,
                'is_trial'    => false,
                'sort_order'  => 1,
            ],
            [
                'name'        => 'HYROX 1-Month',
                'description' => 'Unlimited HYROX classes for 1 month. No lock-in.',
                'credits'     => 999,
                'period_days' => 30,
                'price'       => 280,
                'badge'       => 'HYROX Only',
                'is_active'   => true,
                'is_trial'    => false,
                'sort_order'  => 2,
            ],
            [
                'name'        => 'HYROX 3-Month',
                'description' => 'Unlimited HYROX classes for 3 months. RM250/month.',
                'credits'     => 999,
                'period_days' => 90,
                'price'       => 750,
                'badge'       => 'HYROX Only',
                'is_active'   => true,
                'is_trial'    => false,
                'sort_order'  => 3,
            ],
            [
                'name'        => 'HYROX 6-Month',
                'description' => 'Unlimited HYROX classes for 6 months. RM220/month.',
                'credits'     => 999,
                'period_days' => 180,
                'price'       => 1320,
                'badge'       => 'Best Value',
                'is_active'   => true,
                'is_trial'    => false,
                'sort_order'  => 4,
            ],
            [
                'name'        => 'Full Unlimited 1-Month',
                'description' => 'Access to all classes for 1 month. No lock-in.',
                'credits'     => 999,
                'period_days' => 30,
                'price'       => 300,
                'badge'       => 'All Classes',
                'is_active'   => true,
                'is_trial'    => false,
                'sort_order'  => 5,
            ],
            [
                'name'        => 'Full Unlimited 6-Month',
                'description' => 'Access to all classes for 6 months. RM250/month.',
                'credits'     => 999,
                'period_days' => 180,
                'price'       => 1500,
                'badge'       => 'Popular',
                'is_active'   => true,
                'is_trial'    => false,
                'sort_order'  => 6,
            ],
            [
                'name'        => 'Full Unlimited 12-Month',
                'description' => 'Access to all classes for 12 months. RM235/month.',
                'credits'     => 999,
                'period_days' => 365,
                'price'       => 2820,
                'badge'       => 'Best Value',
                'is_active'   => true,
                'is_trial'    => false,
                'sort_order'  => 7,
            ],
        ];

        foreach ($packages as $data) {
            Package::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}
