<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use MiPress\Core\Http\Controllers\EntryController;

Route::get('theme-files/{theme}/{path}', [EntryController::class, 'asset'])
    ->where('path', '.*')
    ->name('mipress.theme.asset');

// CMS catch-all resolves collection routes dynamically from DB.
// Must be registered last so it does not shadow admin/api routes.
Route::get('{path}', EntryController::class)
    ->where('path', '.+')
    ->name('mipress.entry.show');
