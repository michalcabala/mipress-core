<?php

declare(strict_types=1);

namespace MiPress\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use MiPress\Core\Models\GlobalSet;

class GlobalSetSeeder extends Seeder
{
    public function run(): void
    {
        $sets = [
            [
                'handle' => 'general',
                'title' => 'Obecné',
                'data' => [
                    'site_name' => '',
                    'email' => '',
                    'phone' => '',
                    'address' => '',
                ],
            ],
            [
                'handle' => 'site',
                'title' => 'Nastavení webu',
                'data' => [
                    'default_locale' => 'cs',
                    'date_format' => 'j. F Y',
                    'per_page' => 12,
                ],
            ],
            [
                'handle' => 'social',
                'title' => 'Sociální sítě',
                'data' => [
                    'facebook' => '',
                    'instagram' => '',
                    'youtube' => '',
                    'linkedin' => '',
                ],
            ],
            [
                'handle' => 'seo',
                'title' => 'SEO',
                'data' => [
                    'meta_title_suffix' => '',
                    'meta_description' => '',
                    'gtm_code' => '',
                ],
            ],
        ];

        foreach ($sets as $set) {
            GlobalSet::firstOrCreate(
                ['handle' => $set['handle']],
                [
                    'title' => $set['title'],
                    'data' => $set['data'],
                ],
            );
        }
    }
}
