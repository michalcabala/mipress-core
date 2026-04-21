<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use MiPress\Core\Http\Controllers\EntryController;
use MiPress\Core\Http\Controllers\PagePreviewController;
use MiPress\Core\Http\Controllers\PreviewController;

Route::get('/', [EntryController::class, 'home'])
    ->name('home');

Route::get('theme-files/{theme}/{path}', [EntryController::class, 'asset'])
    ->where('path', '.*')
    ->name('mipress.theme.asset');

Route::get('preview/{entry}', PreviewController::class)
    ->middleware('signed')
    ->name('preview.entry');

Route::get('preview/page/{page}', PagePreviewController::class)
    ->middleware('signed')
    ->name('preview.page');

// CMS catch-all resolves collection routes dynamically from DB.
// Must be registered last so it does not shadow admin/api routes.
Route::get('{path}', EntryController::class)
    ->where('path', '^(?!mpcp(/|$)).+')
    ->name('mipress.entry.show');
