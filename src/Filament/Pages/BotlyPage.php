<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Pages;

use Awcodes\Botly\Models\Botly;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use MiPress\Core\Filament\Clusters\SeoCluster;

class BotlyPage extends \Awcodes\Botly\Filament\Pages\BotlyPage
{
    protected static ?string $cluster = SeoCluster::class;

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('mipress::admin.plugin.botly_title');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return 'fal-user-robot';
    }

    public static function canAccess(): bool
    {
        return auth()->user() !== null && Gate::allows('viewAny', Botly::class);
    }
}
