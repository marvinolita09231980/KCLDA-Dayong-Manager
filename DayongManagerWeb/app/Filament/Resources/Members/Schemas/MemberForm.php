<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Filament\Resources\Members\Pages\CreateMember;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class MemberForm
{
    public static function normalizeOptionalText(array $data): array
    {
        foreach (['middle_name', 'sponsor_name', 'contact_number', 'beneficiary_name', 'beneficiary_contact', 'remarks', 'claimed_benefits', 'claim_received_by'] as $field) {
            $data[$field] = $data[$field] ?? '';
        }

        return $data;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])->components([Section::make('Personal information')->columns(['default' => 1, 'md' => 2, 'xl' => 3])->columnSpanFull()->schema([
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('middle_name')
                    ->default(''),
                Textarea::make('address')
                    ->required()
                    ->default('')
                    ->columnSpanFull(),
                DatePicker::make('birth_date'),
                TextInput::make('council')
                    ->required(),
            ]), Section::make('Membership')->columns(['default' => 1, 'md' => 2, 'xl' => 3])->columnSpanFull()->schema([
                Select::make('membership_type')->options(['Brother Knight'=>'Brother Knight','Associate Member'=>'Associate Member'])->required()->default('Brother Knight'),
                TextInput::make('sponsor_name')
                    ->default(''),
                TextInput::make('contact_number')
                    ->default(''),
                TextInput::make('beneficiary_name')
                    ->default(''),
                TextInput::make('beneficiary_contact')
                    ->default(''),
                Toggle::make('is_fourth_degree')
                    ->required(),
                Select::make('member_status')->options(['Active'=>'Active','Inactive'=>'Inactive','Expelled'=>'Expelled','Deceased'=>'Deceased'])->required()->default('Active')->live(),
                Textarea::make('remarks')
                    ->default('')
                    ->columnSpanFull(),
                DatePicker::make('registration_date')
                    ->required(fn ($livewire): bool => $livewire instanceof CreateMember),
                Select::make('start_cycle_id')
                    ->relationship('startCycle', 'name')->searchable()->preload()
                    ->required(fn ($livewire): bool => $livewire instanceof CreateMember),
            ]), Section::make('Beneficiary and claims')->columns(['default' => 1, 'md' => 2, 'xl' => 3])->columnSpanFull()->schema([
                DatePicker::make('date_of_death')->visible(fn ($get) => $get('member_status') === 'Deceased'),
                Textarea::make('claimed_benefits')
                    ->default('')
                    ->columnSpanFull(),
                DatePicker::make('service_date'),
                DatePicker::make('claim_received_date'),
                TextInput::make('claim_received_by')
                    ->default(''),
            ])]);
    }
}
