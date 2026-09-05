<?php

namespace App\Filament\Resources\CollectionCycles;

use App\Filament\Resources\CollectionCycles\Pages\CreateCollectionCycle;
use App\Filament\Resources\CollectionCycles\Pages\EditCollectionCycle;
use App\Filament\Resources\CollectionCycles\Pages\ListCollectionCycles;
use App\Filament\Resources\CollectionCycles\Schemas\CollectionCycleForm;
use App\Filament\Resources\CollectionCycles\Tables\CollectionCyclesTable;
use App\Models\CollectionCycle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CollectionCycleResource extends Resource
{
    protected static ?string $model = CollectionCycle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function canViewAny(): bool { return auth()->user()?->hasPermission('collections.view') ?? false; }
    public static function canCreate(): bool { return auth()->user()?->hasPermission('collections.manage') ?? false; }
    public static function canEdit($record): bool { return static::canCreate(); }
    public static function canDelete($record): bool { return static::canCreate(); }

    public static function form(Schema $schema): Schema
    {
        return CollectionCycleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CollectionCyclesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCollectionCycles::route('/'),
            'create' => CreateCollectionCycle::route('/create'),
            'edit' => EditCollectionCycle::route('/{record}/edit'),
        ];
    }
}
