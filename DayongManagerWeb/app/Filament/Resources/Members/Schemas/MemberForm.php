<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([Section::make('Personal information')->columns(3)->schema([
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('middle_name')
                    ->required()
                    ->default(''),
                Textarea::make('address')
                    ->required()
                    ->default('')
                    ->columnSpanFull(),
                DatePicker::make('birth_date'),
                TextInput::make('council')
                    ->required(),
            ]), Section::make('Membership')->columns(3)->schema([
                Select::make('membership_type')->options(['Brother Knight'=>'Brother Knight','Associate Member'=>'Associate Member'])->required()->default('Brother Knight'),
                TextInput::make('sponsor_name')
                    ->required()
                    ->default(''),
                TextInput::make('contact_number')
                    ->required()
                    ->default(''),
                TextInput::make('beneficiary_name')
                    ->required()
                    ->default(''),
                TextInput::make('beneficiary_contact')
                    ->required()
                    ->default(''),
                Toggle::make('is_fourth_degree')
                    ->required(),
                Select::make('member_status')->options(['Active'=>'Active','Inactive'=>'Inactive','Expelled'=>'Expelled','Deceased'=>'Deceased'])->required()->default('Active')->live(),
                Textarea::make('remarks')
                    ->required()
                    ->default('')
                    ->columnSpanFull(),
                DatePicker::make('registration_date'),
                Select::make('start_cycle_id')
                    ->relationship('startCycle', 'name')->searchable()->preload(),
            ]), Section::make('Beneficiary and claims')->columns(3)->schema([
                DatePicker::make('date_of_death')->visible(fn ($get) => $get('member_status') === 'Deceased'),
                Textarea::make('claimed_benefits')
                    ->required()
                    ->default('')
                    ->columnSpanFull(),
                DatePicker::make('service_date'),
                DatePicker::make('claim_received_date'),
                TextInput::make('claim_received_by')
                    ->required()
                    ->default(''),
            ])]);
    }
}
