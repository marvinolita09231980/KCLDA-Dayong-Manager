<?php

namespace App\Filament\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;

class Login extends \Filament\Auth\Pages\Login
{
    public function getHeading(): string { return 'Welcome back'; }
    public function getSubheading(): ?string { return 'Sign in to care for your community.'; }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')->label('Username')->required()->autocomplete('username')->autofocus();
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return ['username' => $data['username'], 'password' => $data['password']];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
