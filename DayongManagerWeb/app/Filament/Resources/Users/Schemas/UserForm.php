<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('username')->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()->unique(ignoreRecord: true),

                TextInput::make('password')
                    ->password()
                    ->revealable()->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state)),
                Toggle::make('active')
                    ->required(),
                Toggle::make('is_admin')
                    ->required(),
                CheckboxList::make('permissions')->options([
                    'members.view'=>'View members','members.manage'=>'Manage members','collections.view'=>'View collections','collections.manage'=>'Manage collections',
                    'compliance.view'=>'View compliance','ledger.view'=>'View bank ledger','ledger.manage'=>'Manage bank ledger','disbursements.view'=>'View disbursements','disbursements.manage'=>'Manage disbursements','users.manage'=>'Manage users',
                ])->columns(2)->columnSpanFull(),
            ]);
    }
}


