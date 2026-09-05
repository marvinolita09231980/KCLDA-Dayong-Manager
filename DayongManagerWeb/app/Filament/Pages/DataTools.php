<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class DataTools extends Page
{
    protected static ?string $title = 'Import, Export & Database Backup';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.data-tools';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->active && ($user->hasPermission('tools.import') || $user->hasPermission('tools.export') || $user->hasPermission('tools.backup'));
    }
}
