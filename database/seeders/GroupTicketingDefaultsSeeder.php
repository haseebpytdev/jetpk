<?php

namespace Database\Seeders;

use App\Enums\GroupHomepageTileTargetType;
use App\Models\GroupCategory;
use App\Models\GroupHomepageTile;
use Illuminate\Database\Seeder;

class GroupTicketingDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        GroupCategory::query()->firstOrCreate(
            ['slug' => 'umrah'],
            ['name' => 'Umrah', 'is_active' => true, 'sort_order' => 1],
        );

        GroupCategory::query()->firstOrCreate(
            ['slug' => 'featured'],
            ['name' => 'Featured', 'is_active' => true, 'sort_order' => 2],
        );

        $defaults = [
            [
                'title' => 'All Groups',
                'target_type' => GroupHomepageTileTargetType::All,
                'target_value' => null,
                'sort_order' => 1,
            ],
            [
                'title' => 'LHE-MCT',
                'target_type' => GroupHomepageTileTargetType::Sector,
                'target_value' => 'LHE-MCT',
                'sort_order' => 2,
            ],
            [
                'title' => 'Umrah Groups',
                'target_type' => GroupHomepageTileTargetType::Category,
                'target_value' => 'umrah',
                'sort_order' => 3,
            ],
            [
                'title' => 'Featured',
                'target_type' => GroupHomepageTileTargetType::Category,
                'target_value' => 'featured',
                'sort_order' => 4,
            ],
        ];

        foreach ($defaults as $tile) {
            GroupHomepageTile::query()->firstOrCreate(
                ['title' => $tile['title']],
                [
                    'image_path' => null,
                    'target_type' => $tile['target_type'],
                    'target_value' => $tile['target_value'],
                    'is_active' => true,
                    'sort_order' => $tile['sort_order'],
                ],
            );
        }
    }
}
