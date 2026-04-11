<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class RevisionDiffPresenter
{
    /**
     * @var array<int, string>
     */
    private const SECTION_ORDER = [
        'Základ',
        'SEO',
        'Vlastní pole',
        'Ostatní',
    ];

    /**
     * @var array<int, string>
     */
    private const MASON_PATHS = [
        'content',
        'data.content',
    ];

    /**
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'title' => 'Titulek',
        'slug' => 'Slug',
        'status' => 'Stav',
        'published_at' => 'Datum publikování',
        'scheduled_at' => 'Naplánovat na',
        'meta_title' => 'SEO titulek',
        'meta_description' => 'SEO popis',
        'og_image_id' => 'OG obrázek',
        'featured_image_id' => 'Hlavní obrázek',
        'author_id' => 'Autor',
        'parent_id' => 'Nadřazený záznam',
        'sort_order' => 'Pořadí',
        'review_note' => 'Poznámka recenze',
        'locale' => 'Jazyk',
        'collection_id' => 'Kolekce',
        'blueprint_id' => 'Blueprint',
        'data' => 'Vlastní data',
        'content' => 'Obsah (Mason)',
        'data.content' => 'Obsah (Mason)',
    ];

    public function summarizeSnapshot(array $snapshot): string
    {
        $labels = $this->collectSnapshotLabels($snapshot);

        if ($labels === []) {
            return 'Revize neobsahuje žádná uložená pole.';
        }

        $visibleLabels = array_slice($labels, 0, 4);
        $summary = implode(', ', $visibleLabels);
        $remainingCount = count($labels) - count($visibleLabels);

        if ($remainingCount > 0) {
            $summary .= ' +'.$remainingCount.' další';
        }

        return $summary;
    }

    public function summarizeSnapshotMeta(array $snapshot): string
    {
        $fieldCount = count($this->collectSnapshotLabels($snapshot));

        if ($fieldCount === 0) {
            return 'Bez uloženého obsahu';
        }

        return 'Uloženo '.$fieldCount.' polí';
    }

    public function renderComparison(array $leftData, array $rightData, string $leftLabel, string $rightLabel): HtmlString
    {
        $fieldChanges = $this->collectChanges($leftData, $rightData);
        $masonComparisons = $this->collectMasonComparisons($leftData, $rightData);

        if ($fieldChanges === [] && $masonComparisons === []) {
            return new HtmlString(
                '<div class="rounded-xl bg-white p-4 text-sm text-gray-600 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10">'
                .'Vybrané verze neobsahují žádné rozdíly.'
                .'</div>',
            );
        }

        $changeCount = count($fieldChanges) + collect($masonComparisons)
            ->sum(fn (array $comparison): int => (int) ($comparison['summary']['total'] ?? 0));

        $html = '<div class="space-y-4 text-gray-950 dark:text-white">'
            .'<div class="grid gap-3 md:grid-cols-3">'
            .$this->renderMetaCard('Porovnávané položky', (string) $changeCount, 'Výchozí zobrazení ukazuje jen změněná pole a bloky.')
            .$this->renderMetaCard('Levá verze', $leftLabel, 'Starší nebo referenční revize.')
            .$this->renderMetaCard('Pravá verze', $rightLabel, 'Novější revize nebo aktuální stav.')
            .'</div>'
            .$this->renderStandardSections($fieldChanges, $leftLabel, $rightLabel)
            .$this->renderMasonSections($masonComparisons, $leftLabel, $rightLabel)
            .'</div>';

        return new HtmlString($html);
    }

    /**
     * @return array<int, array{path: string, field: string, section: string, old: mixed, new: mixed}>
     */
    private function collectChanges(array $leftData, array $rightData): array
    {
        $leftFlat = $this->flattenForDiff($leftData);
        $rightFlat = $this->flattenForDiff($rightData);

        $allPaths = array_values(array_unique([
            ...array_keys($leftFlat),
            ...array_keys($rightFlat),
        ]));

        sort($allPaths);

        $changes = [];

        foreach ($allPaths as $path) {
            if ($this->isMasonPath($path)) {
                continue;
            }

            $leftValue = $leftFlat[$path] ?? null;
            $rightValue = $rightFlat[$path] ?? null;

            if ($this->valuesAreEqual($leftValue, $rightValue)) {
                continue;
            }

            $changes[] = [
                'path' => $path,
                'field' => $this->resolveFieldLabel($path),
                'section' => $this->resolveSection($path),
                'old' => $leftValue,
                'new' => $rightValue,
            ];
        }

        return $changes;
    }

    /**
     * @return array<int, array{path: string, label: string, changes: array<int, array{type: string, left: ?array<string, mixed>, right: ?array<string, mixed>, moved: bool}>, summary: array{added: int, removed: int, moved: int, changed: int, total: int}}>
     */
    private function collectMasonComparisons(array $leftData, array $rightData): array
    {
        $comparisons = [];
        $seenSignatures = [];

        foreach (self::MASON_PATHS as $path) {
            $leftRaw = Arr::get($leftData, $path);
            $rightRaw = Arr::get($rightData, $path);

            if (! is_array($leftRaw) && ! is_array($rightRaw)) {
                continue;
            }

            $leftBlocks = $this->normalizeMasonBlocks(is_array($leftRaw) ? $leftRaw : []);
            $rightBlocks = $this->normalizeMasonBlocks(is_array($rightRaw) ? $rightRaw : []);
            $signature = sha1(implode('|', array_column($leftBlocks, 'hash')).'||'.implode('|', array_column($rightBlocks, 'hash')));

            if (isset($seenSignatures[$signature])) {
                continue;
            }

            $seenSignatures[$signature] = true;

            $changes = $this->detectMasonChanges($leftBlocks, $rightBlocks);

            if ($changes === []) {
                continue;
            }

            $comparisons[] = [
                'path' => $path,
                'label' => $this->resolveFieldLabel($path),
                'changes' => $changes,
                'summary' => $this->summarizeMasonChanges($changes),
            ];
        }

        return $comparisons;
    }

    /**
     * @param  array<int, array{type: string, left: ?array<string, mixed>, right: ?array<string, mixed>, moved: bool}>  $changes
     * @return array{added: int, removed: int, moved: int, changed: int, total: int}
     */
    private function summarizeMasonChanges(array $changes): array
    {
        $summary = [
            'added' => 0,
            'removed' => 0,
            'moved' => 0,
            'changed' => 0,
            'total' => count($changes),
        ];

        foreach ($changes as $change) {
            if ($change['type'] === 'added') {
                $summary['added']++;

                continue;
            }

            if ($change['type'] === 'removed') {
                $summary['removed']++;

                continue;
            }

            if ($change['type'] === 'moved') {
                $summary['moved']++;

                continue;
            }

            if ($change['type'] === 'changed') {
                $summary['changed']++;

                if (($change['moved'] ?? false) === true) {
                    $summary['moved']++;
                }
            }
        }

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fieldChanges
     */
    private function renderStandardSections(array $fieldChanges, string $leftLabel, string $rightLabel): string
    {
        if ($fieldChanges === []) {
            return '';
        }

        $grouped = collect($fieldChanges)
            ->groupBy('section')
            ->map(fn ($items): array => $items->all())
            ->all();

        $orderedSectionNames = [];

        foreach (self::SECTION_ORDER as $sectionName) {
            if (isset($grouped[$sectionName])) {
                $orderedSectionNames[] = $sectionName;
            }
        }

        foreach (array_keys($grouped) as $sectionName) {
            if (! in_array($sectionName, $orderedSectionNames, true)) {
                $orderedSectionNames[] = (string) $sectionName;
            }
        }

        return collect($orderedSectionNames)
            ->map(function (string $sectionName) use ($grouped, $leftLabel, $rightLabel): string {
                /** @var array<int, array<string, mixed>> $rows */
                $rows = $grouped[$sectionName] ?? [];

                return $this->renderStandardSection($sectionName, $rows, $leftLabel, $rightLabel);
            })
            ->implode('');
    }

    /**
     * @param  array<int, array<string, mixed>>  $changes
     */
    private function renderStandardSection(string $sectionName, array $changes, string $leftLabel, string $rightLabel): string
    {
        $rows = collect($changes)
            ->map(function (array $change): string {
                return '<tr class="align-top">'
                    .'<td class="px-4 py-3">'
                    .'<p class="text-sm font-medium text-gray-900 dark:text-white">'.e((string) $change['field']).'</p>'
                    .'<p class="mt-1 inline-flex rounded-md bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600 dark:bg-white/5 dark:text-gray-400">'.e((string) $change['path']).'</p>'
                    .'</td>'
                    .'<td class="px-4 py-3">'.$this->renderValue($change['old'] ?? null, 'warning').'</td>'
                    .'<td class="px-4 py-3">'.$this->renderValue($change['new'] ?? null, 'success').'</td>'
                    .'</tr>';
            })
            ->implode('');

        return '<section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">'
            .'<div class="border-b border-gray-200/90 px-4 py-3 dark:border-white/10">'
            .'<h3 class="text-sm font-semibold text-gray-900 dark:text-white">'.e($sectionName).'</h3>'
            .'</div>'
            .'<div class="overflow-x-auto">'
            .'<table class="min-w-full divide-y divide-gray-200/90 dark:divide-white/10">'
            .'<thead class="bg-gray-50/90 dark:bg-white/5">'
            .'<tr>'
            .'<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pole</th>'
            .'<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">'.e($leftLabel).'</th>'
            .'<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">'.e($rightLabel).'</th>'
            .'</tr>'
            .'</thead>'
            .'<tbody class="divide-y divide-gray-100/90 dark:divide-white/10">'
            .$rows
            .'</tbody>'
            .'</table>'
            .'</div>'
            .'</section>';
    }

    /**
     * @param  array<int, array{path: string, label: string, changes: array<int, array{type: string, left: ?array<string, mixed>, right: ?array<string, mixed>, moved: bool}>, summary: array{added: int, removed: int, moved: int, changed: int, total: int}}>  $comparisons
     */
    private function renderMasonSections(array $comparisons, string $leftLabel, string $rightLabel): string
    {
        if ($comparisons === []) {
            return '';
        }

        return collect($comparisons)
            ->map(fn (array $comparison): string => $this->renderMasonSection($comparison, $leftLabel, $rightLabel))
            ->implode('');
    }

    /**
     * @param  array{path: string, label: string, changes: array<int, array{type: string, left: ?array<string, mixed>, right: ?array<string, mixed>, moved: bool}>, summary: array{added: int, removed: int, moved: int, changed: int, total: int}}  $comparison
     */
    private function renderMasonSection(array $comparison, string $leftLabel, string $rightLabel): string
    {
        $summary = $comparison['summary'];

        $cards = collect($comparison['changes'])
            ->map(fn (array $change): string => $this->renderMasonChangeCard($change, $leftLabel, $rightLabel))
            ->implode('');

        return '<section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">'
            .'<div class="border-b border-gray-200/90 px-4 py-3 dark:border-white/10">'
            .'<h3 class="text-sm font-semibold text-gray-900 dark:text-white">'.e($comparison['label']).'</h3>'
            .'<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Rich diff bloků s heuristikou přesunů.</p>'
            .'</div>'
            .'<div class="grid gap-3 border-b border-gray-200/90 p-4 md:grid-cols-5 dark:border-white/10">'
            .$this->renderMetaCardInline('Změny', (string) $summary['total'])
            .$this->renderMetaCardInline('Přidáno', (string) $summary['added'])
            .$this->renderMetaCardInline('Odebráno', (string) $summary['removed'])
            .$this->renderMetaCardInline('Přesunuto', (string) $summary['moved'])
            .$this->renderMetaCardInline('Upraveno', (string) $summary['changed'])
            .'</div>'
            .'<div class="space-y-3 p-4">'
            .$cards
            .'</div>'
            .'</section>';
    }

    private function renderMetaCardInline(string $label, string $value): string
    {
        return '<div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-200/80 dark:bg-white/5 dark:ring-white/10">'
            .'<p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">'.e($label).'</p>'
            .'<p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">'.e($value).'</p>'
            .'</div>';
    }

    /**
     * @param  array{type: string, left: ?array<string, mixed>, right: ?array<string, mixed>, moved: bool}  $change
     */
    private function renderMasonChangeCard(array $change, string $leftLabel, string $rightLabel): string
    {
        $badge = $this->renderMasonBadge($change['type'], (bool) ($change['moved'] ?? false));

        return '<article class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">'
            .'<div class="mb-3">'.$badge.'</div>'
            .'<div class="grid gap-3 md:grid-cols-2">'
            .$this->renderMasonBlockSide($leftLabel, $change['left'] ?? null, 'warning')
            .$this->renderMasonBlockSide($rightLabel, $change['right'] ?? null, 'success')
            .'</div>'
            .'</article>';
    }

    private function renderMasonBadge(string $type, bool $moved): string
    {
        if ($type === 'added') {
            return '<span class="inline-flex items-center rounded-md border border-emerald-300 bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-300">Přidaný blok</span>';
        }

        if ($type === 'removed') {
            return '<span class="inline-flex items-center rounded-md border border-rose-300 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 dark:border-rose-900/70 dark:bg-rose-500/10 dark:text-rose-300">Odebraný blok</span>';
        }

        if ($type === 'moved') {
            return '<span class="inline-flex items-center rounded-md border border-sky-300 bg-sky-50 px-2 py-1 text-xs font-semibold text-sky-700 dark:border-sky-900/70 dark:bg-sky-500/10 dark:text-sky-300">Přesunutý blok</span>';
        }

        if ($moved) {
            return '<span class="inline-flex items-center rounded-md border border-violet-300 bg-violet-50 px-2 py-1 text-xs font-semibold text-violet-700 dark:border-violet-900/70 dark:bg-violet-500/10 dark:text-violet-300">Upravený + přesunutý blok</span>';
        }

        return '<span class="inline-flex items-center rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 dark:border-amber-900/70 dark:bg-amber-500/10 dark:text-amber-300">Upravený blok</span>';
    }

    /**
     * @param  array<string, mixed>|null  $block
     */
    private function renderMasonBlockSide(string $label, ?array $block, string $tone): string
    {
        if ($block === null) {
            return '<div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-500 dark:border-white/15 dark:bg-white/5 dark:text-gray-400">'
                .'<p class="text-xs font-semibold uppercase tracking-wide">'.e($label).'</p>'
                .'<p class="mt-2">Žádný blok</p>'
                .'</div>';
        }

        $position = ((int) ($block['index'] ?? 0)) + 1;
        $identifier = is_string($block['id'] ?? null) ? ' · ID '.$block['id'] : '';
        $preview = trim((string) ($block['preview'] ?? ''));

        return '<div class="rounded-lg bg-white p-3 ring-1 ring-gray-200/80 dark:bg-gray-900/80 dark:ring-white/10">'
            .'<p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">'.e($label).'</p>'
            .'<p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">'.e((string) ($block['type'] ?? 'Blok')).'</p>'
            .'<p class="text-xs text-gray-500 dark:text-gray-400">Pozice #'.e((string) $position).e($identifier).'</p>'
            .'<p class="mt-2 text-sm text-gray-700 dark:text-gray-200">'.($preview !== '' ? e($preview) : 'Bez textového náhledu').'</p>'
            .'<div class="mt-2">'.$this->renderValue($block['data'] ?? null, $tone).'</div>'
            .'</div>';
    }

    /**
     * @param  array<int, array<string, mixed>>  $leftBlocks
     * @param  array<int, array<string, mixed>>  $rightBlocks
     * @return array<int, array{type: string, left: ?array<string, mixed>, right: ?array<string, mixed>, moved: bool}>
     */
    private function detectMasonChanges(array $leftBlocks, array $rightBlocks): array
    {
        $changes = [];
        $matchedLeft = [];
        $matchedRight = [];

        $rightIdMap = [];

        foreach ($rightBlocks as $rightIndex => $rightBlock) {
            $identifier = $rightBlock['id'] ?? null;

            if (! is_string($identifier) || $identifier === '') {
                continue;
            }

            $rightIdMap[$identifier][] = $rightIndex;
        }

        foreach ($leftBlocks as $leftIndex => $leftBlock) {
            $identifier = $leftBlock['id'] ?? null;

            if (! is_string($identifier) || $identifier === '' || ! isset($rightIdMap[$identifier]) || $rightIdMap[$identifier] === []) {
                continue;
            }

            $rightIndex = array_shift($rightIdMap[$identifier]);
            $rightBlock = $rightBlocks[$rightIndex];

            $matchedLeft[$leftIndex] = true;
            $matchedRight[$rightIndex] = true;

            if ($leftBlock['hash'] === $rightBlock['hash']) {
                if ($leftIndex !== $rightIndex) {
                    $changes[] = [
                        'type' => 'moved',
                        'left' => $leftBlock,
                        'right' => $rightBlock,
                        'moved' => true,
                    ];
                }

                continue;
            }

            $changes[] = [
                'type' => 'changed',
                'left' => $leftBlock,
                'right' => $rightBlock,
                'moved' => $leftIndex !== $rightIndex,
            ];
        }

        $rightHashMap = [];

        foreach ($rightBlocks as $rightIndex => $rightBlock) {
            if (isset($matchedRight[$rightIndex])) {
                continue;
            }

            $rightHashMap[$rightBlock['hash']][] = $rightIndex;
        }

        foreach ($leftBlocks as $leftIndex => $leftBlock) {
            if (isset($matchedLeft[$leftIndex])) {
                continue;
            }

            if (isset($rightHashMap[$leftBlock['hash']]) && $rightHashMap[$leftBlock['hash']] !== []) {
                $rightIndex = array_shift($rightHashMap[$leftBlock['hash']]);
                $rightBlock = $rightBlocks[$rightIndex];

                $matchedLeft[$leftIndex] = true;
                $matchedRight[$rightIndex] = true;

                if ($leftIndex !== $rightIndex) {
                    $changes[] = [
                        'type' => 'moved',
                        'left' => $leftBlock,
                        'right' => $rightBlock,
                        'moved' => true,
                    ];
                }

                continue;
            }

            $changes[] = [
                'type' => 'removed',
                'left' => $leftBlock,
                'right' => null,
                'moved' => false,
            ];
        }

        foreach ($rightBlocks as $rightIndex => $rightBlock) {
            if (isset($matchedRight[$rightIndex])) {
                continue;
            }

            $changes[] = [
                'type' => 'added',
                'left' => null,
                'right' => $rightBlock,
                'moved' => false,
            ];
        }

        usort($changes, function (array $first, array $second): int {
            $firstIndex = (int) ($first['right']['index'] ?? $first['left']['index'] ?? PHP_INT_MAX);
            $secondIndex = (int) ($second['right']['index'] ?? $second['left']['index'] ?? PHP_INT_MAX);

            return $firstIndex <=> $secondIndex;
        });

        return $changes;
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMasonBlocks(array $blocks): array
    {
        $normalized = [];
        $list = array_is_list($blocks) ? $blocks : array_values($blocks);

        foreach ($list as $index => $block) {
            $normalized[] = $this->normalizeMasonBlock($block, (int) $index);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMasonBlock(mixed $block, int $index): array
    {
        if (! is_array($block)) {
            $normalizedValue = $this->normalizeForComparison($block);

            return [
                'index' => $index,
                'id' => null,
                'type' => 'Neznámý blok',
                'hash' => $this->hashValue($normalizedValue),
                'preview' => $this->extractReadablePreview($block),
                'data' => $normalizedValue,
            ];
        }

        $identifier = $this->resolveBlockIdentifier($block);
        $type = $this->resolveBlockType($block);
        $payload = $this->resolveBlockPayload($block);
        $normalizedPayload = $this->normalizeForComparison($payload);

        return [
            'index' => $index,
            'id' => $identifier,
            'type' => $type,
            'hash' => $this->hashValue([$type, $normalizedPayload]),
            'preview' => $this->extractReadablePreview($payload),
            'data' => $normalizedPayload,
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function resolveBlockIdentifier(array $block): ?string
    {
        foreach (['id', 'uuid', '_uuid', 'key', '_key'] as $identifierKey) {
            $identifier = $block[$identifierKey] ?? null;

            if (is_string($identifier) && trim($identifier) !== '') {
                return trim($identifier);
            }

            if (is_int($identifier)) {
                return (string) $identifier;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function resolveBlockType(array $block): string
    {
        foreach (['type', 'name', 'block', 'component'] as $typeKey) {
            $type = $block[$typeKey] ?? null;

            if (is_string($type) && trim($type) !== '') {
                return Str::headline(str_replace(['_', '-'], ' ', trim($type)));
            }
        }

        return 'Blok';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function resolveBlockPayload(array $block): mixed
    {
        if (array_key_exists('data', $block)) {
            return $block['data'];
        }

        if (array_key_exists('content', $block)) {
            return $block['content'];
        }

        return Arr::except($block, ['id', 'uuid', '_uuid', 'key', '_key', 'type', 'name', 'block', 'component']);
    }

    private function hashValue(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            return sha1(serialize($value));
        }

        return sha1($encoded);
    }

    private function extractReadablePreview(mixed $value): string
    {
        if (is_string($value)) {
            return (string) Str::of(strip_tags($value))
                ->squish()
                ->limit(180);
        }

        if (! is_array($value)) {
            if (is_bool($value)) {
                return $value ? 'Ano' : 'Ne';
            }

            if ($value === null) {
                return '';
            }

            return (string) Str::limit((string) $value, 180);
        }

        $fragments = [];
        $this->collectReadableFragments($value, $fragments);

        return (string) Str::of(implode(' · ', $fragments))
            ->squish()
            ->limit(180);
    }

    /**
     * @param  array<int, string>  $fragments
     */
    private function collectReadableFragments(mixed $value, array &$fragments): void
    {
        if (count($fragments) >= 4) {
            return;
        }

        if (is_string($value)) {
            $normalized = trim(strip_tags($value));

            if ($normalized !== '') {
                $fragments[] = (string) Str::limit($normalized, 80);
            }

            return;
        }

        if (! is_array($value)) {
            if (is_bool($value)) {
                $fragments[] = $value ? 'Ano' : 'Ne';

                return;
            }

            if ($value !== null && is_scalar($value)) {
                $fragments[] = (string) Str::limit((string) $value, 80);
            }

            return;
        }

        foreach ($value as $item) {
            $this->collectReadableFragments($item, $fragments);

            if (count($fragments) >= 4) {
                return;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function flattenForDiff(mixed $value, string $path = ''): array
    {
        if (! is_array($value)) {
            return $path === '' ? [] : [$path => $value];
        }

        if ($path !== '' && ($this->isMasonPath($path) || array_is_list($value))) {
            return [$path => $value];
        }

        if ($value === []) {
            return $path === '' ? [] : [$path => []];
        }

        $flattened = [];

        foreach ($value as $key => $item) {
            $segment = (string) $key;
            $childPath = $path === '' ? $segment : $path.'.'.$segment;

            if (is_array($item) && ! array_is_list($item) && ! $this->isMasonPath($childPath)) {
                foreach ($this->flattenForDiff($item, $childPath) as $nestedPath => $nestedValue) {
                    $flattened[$nestedPath] = $nestedValue;
                }

                continue;
            }

            $flattened[$childPath] = $item;
        }

        return $flattened;
    }

    private function valuesAreEqual(mixed $leftValue, mixed $rightValue): bool
    {
        return $this->normalizeForComparison($leftValue) === $this->normalizeForComparison($rightValue);
    }

    private function normalizeForComparison(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim(str_replace(["\r\n", "\r"], "\n", $value));
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeForComparison($item);
        }

        if (! array_is_list($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }

    private function isMasonPath(string $path): bool
    {
        if (in_array($path, self::MASON_PATHS, true)) {
            return true;
        }

        return str_starts_with($path, 'content.') || str_starts_with($path, 'data.content.');
    }

    private function resolveSection(string $path): string
    {
        if (str_starts_with($path, 'meta_') || str_starts_with($path, 'og_')) {
            return 'SEO';
        }

        if (str_starts_with($path, 'data.')) {
            return 'Vlastní pole';
        }

        $root = Str::before($path, '.');

        if (in_array($root, ['title', 'slug', 'status', 'published_at', 'scheduled_at', 'author_id', 'parent_id', 'sort_order', 'review_note', 'locale', 'collection_id', 'blueprint_id', 'featured_image_id'], true)) {
            return 'Základ';
        }

        return 'Ostatní';
    }

    private function resolveFieldLabel(string $path): string
    {
        if (isset(self::FIELD_LABELS[$path])) {
            return self::FIELD_LABELS[$path];
        }

        if (str_starts_with($path, 'data.content')) {
            $suffix = ltrim(Str::after($path, 'data.content'), '.');

            if ($suffix === '') {
                return 'Obsah (Mason)';
            }

            return 'Obsah (Mason): '.$this->humanizePath($suffix);
        }

        if (str_starts_with($path, 'content')) {
            $suffix = ltrim(Str::after($path, 'content'), '.');

            if ($suffix === '') {
                return 'Obsah (Mason)';
            }

            return 'Obsah (Mason): '.$this->humanizePath($suffix);
        }

        if (str_starts_with($path, 'data.')) {
            return 'Vlastní pole: '.$this->humanizePath(Str::after($path, 'data.'));
        }

        return $this->humanizePath($path);
    }

    private function humanizePath(string $path): string
    {
        $segments = array_values(array_filter(explode('.', $path), fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return 'Pole';
        }

        $labels = [];

        foreach ($segments as $segment) {
            if (is_numeric($segment)) {
                $labels[] = '#'.(((int) $segment) + 1);

                continue;
            }

            $labels[] = Str::headline(str_replace(['-', '_'], ' ', $segment));
        }

        return implode(' / ', $labels);
    }

    /**
     * @return array<int, string>
     */
    private function collectSnapshotLabels(array $snapshot): array
    {
        $paths = [];

        foreach ($snapshot as $key => $value) {
            $root = (string) $key;

            if ($root === 'data' && is_array($value)) {
                foreach (array_keys($value) as $nestedKey) {
                    $paths[] = 'data.'.(string) $nestedKey;
                }

                continue;
            }

            $paths[] = $root;
        }

        return collect($paths)
            ->map(fn (string $path): string => $this->resolveFieldLabel($path))
            ->unique()
            ->values()
            ->all();
    }

    private function renderMetaCard(string $label, string $value, string $description): string
    {
        return '<div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">'
            .'<p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">'.e($label).'</p>'
            .'<p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">'.e($value).'</p>'
            .'<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">'.e($description).'</p>'
            .'</div>';
    }

    private function renderValue(mixed $value, string $tone): string
    {
        $wrapperClasses = match ($tone) {
            'warning' => 'border-amber-200 bg-amber-50 dark:border-amber-500/20 dark:bg-amber-500/10',
            'success' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/20 dark:bg-emerald-500/10',
            default => 'border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5',
        };

        return '<div class="max-w-xl rounded-lg border '.$wrapperClasses.' px-3 py-2 text-sm text-gray-800 dark:text-gray-100">'
            .$this->formatValue($value)
            .'</div>';
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '<span class="text-gray-400 dark:text-gray-500">—</span>';
        }

        if (is_bool($value)) {
            return $value ? 'Ano' : 'Ne';
        }

        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            return '<pre class="whitespace-pre-wrap wrap-break-word text-xs font-mono leading-5 text-gray-700 dark:text-gray-200">'.e((string) Str::of($json ?: '[]')->limit(2400)).'</pre>';
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return '<span class="text-gray-400 dark:text-gray-500">Prázdná hodnota</span>';
        }

        return nl2br(e((string) str($stringValue)->limit(500)));
    }
}
