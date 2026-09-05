<?php

namespace App\Filament\Resources\Disbursements;

use App\Filament\Resources\Disbursements\Pages\CreateDisbursement;
use App\Filament\Resources\Disbursements\Pages\EditDisbursement;
use App\Filament\Resources\Disbursements\Pages\ListDisbursements;
use App\Filament\Resources\Disbursements\Schemas\DisbursementForm;
use App\Filament\Resources\Disbursements\Tables\DisbursementsTable;
use App\Models\Disbursement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DisbursementResource extends Resource
{
    protected static ?string $model = Disbursement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;
    protected static string|\UnitEnum|null $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 5;
    public static function canViewAny(): bool { return auth()->user()?->hasPermission('disbursements.view') ?? false; }
    public static function canCreate(): bool { return auth()->user()?->hasPermission('disbursements.manage') ?? false; }
    public static function canEdit($record): bool { return static::canCreate(); }
    public static function canDelete($record): bool { return static::canCreate(); }

    public static function form(Schema $schema): Schema
    {
        return DisbursementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DisbursementsTable::configure($table);
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
            'index' => ListDisbursements::route('/'),
            'create' => CreateDisbursement::route('/create'),
            'edit' => EditDisbursement::route('/{record}/edit'),
        ];
    }
}
