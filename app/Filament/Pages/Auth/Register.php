<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/** Landlord self-registration: name, mobile, email, password. Plan is assigned automatically. */
class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            TextInput::make('phone')
                ->label('মোবাইল নম্বর')
                ->tel()
                ->required()
                ->maxLength(11)
                ->placeholder('01XXXXXXXXX')
                ->regex('/^01[3-9]\d{8}$/')
                ->validationMessages(['regex' => 'সঠিক মোবাইল নম্বর দিন (01XXXXXXXXX)'])
                ->unique(User::class, 'phone'),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ]);
    }
}
