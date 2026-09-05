<?php

namespace App\Filament\Resources\Members;

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Schemas\MemberForm;
use App\Filament\Resources\Members\Tables\MembersTable;
use App\Models\Member;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
    protected static string|\UnitEnum|null $navigationGroup = 'Membership';
    protected static ?int $navigationSort = 1;
    public static function canViewAny(): bool { return auth()->user()?->hasPermission('members.view') ?? false; }
    public static function canCreate(): bool { return static::canViewAny() && (auth()->user()?->hasPermission('members.create') ?? false); }
    public static function canEdit($record): bool { return static::canViewAny() && (auth()->user()?->hasPermission('members.edit') ?? false); }
    public static function canDelete($record): bool { return static::canViewAny() && (auth()->user()?->hasPermission('members.delete') ?? false); }
    public static function canDeleteAny(): bool { return static::canDelete(null); }

    public static function form(Schema $schema): Schema
    {
        return MemberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembersTable::configure($table);
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
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }
}
