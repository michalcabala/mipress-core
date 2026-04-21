<?php

declare(strict_types=1);

namespace MiPress\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use MiPress\Core\Models\Blueprint;
use MiPress\Core\Models\Setting;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            $blueprint = Blueprint::query()->firstOrCreate(
                ['handle' => $definition['blueprint']['handle']],
                [
                    'name' => $definition['blueprint']['name'],
                    'fields' => $definition['blueprint']['fields'],
                ],
            );

            if (($blueprint->fields ?? []) === []) {
                $blueprint->forceFill([
                    'name' => $definition['blueprint']['name'],
                    'fields' => $definition['blueprint']['fields'],
                ])->save();
            }

            $setting = Setting::query()->firstOrCreate(
                ['handle' => $definition['handle']],
                [
                    'name' => $definition['name'],
                    'blueprint_id' => $blueprint->getKey(),
                    'data' => $definition['data'],
                    'icon' => $definition['icon'],
                    'sort_order' => $definition['sort_order'],
                ],
            );

            $existingData = is_array($setting->data) ? $setting->data : [];
            $mergedData = array_replace_recursive($definition['data'], $existingData);
            $updates = [];

            if ($setting->blueprint_id === null) {
                $updates['blueprint_id'] = $blueprint->getKey();
            }

            if (! filled($setting->name) || $setting->name === str($definition['handle'])->headline()->toString()) {
                $updates['name'] = $definition['name'];
            }

            if (! filled($setting->icon) || $setting->icon === 'fal-gear') {
                $updates['icon'] = $definition['icon'];
            }

            if ((int) $setting->sort_order >= 999) {
                $updates['sort_order'] = $definition['sort_order'];
            }

            if ($mergedData !== $existingData) {
                $updates['data'] = $mergedData;
            }

            if ($updates !== []) {
                $setting->fill($updates)->save();
            }
        }
    }

    /**
     * @return array<int, array{
     *     handle: string,
     *     name: string,
     *     icon: string,
     *     sort_order: int,
     *     data: array<string, string>,
     *     blueprint: array{
     *         handle: string,
     *         name: string,
     *         fields: array<int, array<string, mixed>>
     *     }
     * }>
     */
    private function definitions(): array
    {
        return [
            [
                'handle' => 'general',
                'name' => 'Obecné',
                'icon' => 'fal-globe',
                'sort_order' => 10,
                'data' => [
                    'site_name' => '',
                    'site_description' => '',
                    'email' => '',
                    'phone' => '',
                    'address' => '',
                ],
                'blueprint' => [
                    'handle' => 'settings_general',
                    'name' => 'Obecné nastavení',
                    'fields' => [
                        [
                            'section' => 'Identita webu',
                            'fields' => [
                                [
                                    'handle' => 'site_name',
                                    'label' => 'Název webu',
                                    'type' => 'text',
                                    'config' => [
                                        'placeholder' => 'Např. MiPress Studio',
                                    ],
                                ],
                                [
                                    'handle' => 'site_description',
                                    'label' => 'Popis webu',
                                    'type' => 'textarea',
                                    'config' => [
                                        'rows' => 3,
                                    ],
                                ],
                            ],
                        ],
                        [
                            'section' => 'Kontakt',
                            'fields' => [
                                [
                                    'handle' => 'email',
                                    'label' => 'E-mail',
                                    'type' => 'text',
                                ],
                                [
                                    'handle' => 'phone',
                                    'label' => 'Telefon',
                                    'type' => 'text',
                                ],
                                [
                                    'handle' => 'address',
                                    'label' => 'Adresa',
                                    'type' => 'textarea',
                                    'config' => [
                                        'rows' => 3,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'handle' => 'social',
                'name' => 'Sociální sítě',
                'icon' => 'fal-share-alt',
                'sort_order' => 20,
                'data' => [
                    'facebook' => '',
                    'instagram' => '',
                    'youtube' => '',
                    'linkedin' => '',
                ],
                'blueprint' => [
                    'handle' => 'settings_social',
                    'name' => 'Sociální sítě',
                    'fields' => [
                        [
                            'section' => 'Profily',
                            'fields' => [
                                [
                                    'handle' => 'facebook',
                                    'label' => 'Facebook URL',
                                    'type' => 'text',
                                ],
                                [
                                    'handle' => 'instagram',
                                    'label' => 'Instagram URL',
                                    'type' => 'text',
                                ],
                                [
                                    'handle' => 'youtube',
                                    'label' => 'YouTube URL',
                                    'type' => 'text',
                                ],
                                [
                                    'handle' => 'linkedin',
                                    'label' => 'LinkedIn URL',
                                    'type' => 'text',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
