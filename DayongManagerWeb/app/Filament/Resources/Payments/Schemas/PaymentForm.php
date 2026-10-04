<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Filament\Resources\Payments\Pages\EditPayment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])->components([
                Select::make('member_id')
                    ->live()
                    ->relationship('member', 'last_name')->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name . ' — ' . $record->council)->searchable(['first_name','middle_name','last_name'])->preload()
                    ->required(),
                Select::make('collection_cycle_id')
                    ->helperText('Choose registration for the joining year, or annual dues for a later year. The member’s registration date determines which fee applies.')
                    ->relationship('collectionCycle', 'name')
                    ->searchable()->preload()->required()->live()
                    ->rules(fn (Get $get, $livewire): array => [
                        function (string $attribute, $value, \Closure $fail) use ($get): void {
                            $member = \App\Models\Member::find($get('member_id'));
                            $cycle = \App\Models\CollectionCycle::find($value);
                            if ($member && $cycle && in_array($cycle->type, ['Registration Fee', 'Annual Dues'], true)
                                && \App\Services\PaymentCycleColumns::year($cycle)
                                && ! \App\Services\PaymentCycleColumns::cycleApplicable($member, $cycle)) {
                                $fail('Registration fees apply only to the member’s registration year; annual dues apply to later years. Check the member’s registration date and select the applicable cycle.');
                            }
                        },
                        Rule::unique('payments', 'collection_cycle_id')
                            ->where('member_id', $get('member_id'))
                            ->ignore($livewire instanceof EditPayment ? $livewire->getRecord()->getKey() : null),
                    ])
                    ->validationMessages(['unique' => 'This member already has a payment for this collection cycle. Edit the existing payment instead.']),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->prefix('₱')->minValue(0.01)
                    ->live(onBlur: true),
                DatePicker::make('date_paid')->default(now()),
                TextInput::make('receipt_number')
                    ->required()
                    ->default(''),
                Textarea::make('notes')
                    ->default('')
                    ->columnSpanFull(),
            ]);
    }
}
