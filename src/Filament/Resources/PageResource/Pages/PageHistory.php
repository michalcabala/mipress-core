<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Filament\Concerns\ConfiguresRevisionTable;
use MiPress\Core\Filament\Resources\Concerns\UsesCurrentPageSubNavigation;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Models\Page;
use MiPress\Core\Models\Revision;

class PageHistory extends ManageRelatedRecords
{
    use ConfiguresRevisionTable;
    use UsesCurrentPageSubNavigation;

    protected static string $resource = PageResource::class;

    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revize';

    protected static ?string $breadcrumb = 'Revize';

    protected static ?string $navigationLabel = 'Revize';

    protected static string|\BackedEnum|null $navigationIcon = 'far-code-compare';

    /**
     * @param  array<string, mixed>  $urlParameters
     */
    protected static function getSubNavigationBadge(array $urlParameters = []): ?string
    {
        $record = $urlParameters['record'] ?? null;

        if ($record instanceof Model) {
            $record = $record->getKey();
        }

        if (blank($record)) {
            return null;
        }

        return (string) Revision::query()
            ->where('revisionable_type', app(Page::class)->getMorphClass())
            ->where('revisionable_id', $record)
            ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'gray';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return 'Počet revizí';
    }

    public function getHeading(): string
    {
        return 'Revize: '.$this->getRecord()->title;
    }

    public function getSubheading(): ?string
    {
        $revisionCount = $this->getRecord()->revisions()->count();

        return $revisionCount === 0
            ? 'Revize se vytvoří při první úpravě obsahu nebo při workflow změně.'
            : 'K dispozici je '.$revisionCount.' uložených verzí. Kliknutím na řádek otevřete detail změn, případně můžete starší verzi obnovit.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubNavigationParameters(): array
    {
        return [
            ...parent::getSubNavigationParameters(),
            'currentPageClass' => static::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $this->configureRevisionTable($table);
    }
}
