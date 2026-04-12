<?php

declare(strict_types=1);

namespace MiPress\Core\Console\Commands;

use Illuminate\Console\Command;
use MiPress\Core\Models\Media;
use MiPress\Core\Services\MediaConversionService;

class RegenerateMediaConversions extends Command
{
    protected $signature = 'media:regenerate-conversions {--ids=* : ID média pro regeneraci}';

    protected $description = 'Zařadí regeneraci konverzí médií do fronty.';

    public function handle(MediaConversionService $service): int
    {
        $ids = collect($this->option('ids'))
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        $query = Media::query();

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids);
        }

        $count = $service->regenerateQuery($query);

        $this->info("Regenerace konverzí byla zařazena pro {$count} médií.");

        return self::SUCCESS;
    }
}
