<?php

namespace App\Filament\Pages\Auth;

use App\Support\Phone;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/** Login with Email OR Bangladesh mobile number + password. */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email বা মোবাইল নম্বর')
            ->required()
            ->maxLength(255)
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $login = trim((string) ($data['email'] ?? ''));

        if (str_contains($login, '@')) {
            return ['email' => $login, 'password' => $data['password'] ?? ''];
        }

        return ['phone' => Phone::normalize($login), 'password' => $data['password'] ?? ''];
    }
}
