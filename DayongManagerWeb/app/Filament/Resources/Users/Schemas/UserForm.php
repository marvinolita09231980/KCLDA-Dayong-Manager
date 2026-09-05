<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])->components([
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
                    ->required()
                    ->default(true),
                Select::make('roles')
                    ->label('User roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('Roles determine which screens and actions this user can access.')
                    ->columnSpanFull(),
            ]);
    }
}
