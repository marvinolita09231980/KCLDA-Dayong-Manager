<?php

namespace App\Services;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\CollectionCycle;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Illuminate\Validation\Rule;

class RecordMemberPayment
{
    public static function make(): Action
    {
        return Action::make('recordPayment')
            ->label('Record payment')
            ->icon('heroicon-o-banknotes')
            ->authorize(fn () => PaymentResource::canCreate())
            ->modalHeading(fn (Member $record) => 'Record payment for '.$record->full_name)
            ->modalDescription(fn (Member $record) => $record->council)
            ->modalWidth('7xl')
            ->modalSubmitActionLabel('Save payment')
            ->modalFooterActions([])
            ->schema(fn (Action $action) => [
                Grid::make(['default' => 1, 'lg' => 2])->schema([
                    Section::make('Payment details')->columns(['default' => 1, 'md' => 2])->schema([
                    Select::make('collection_cycle_id')
                        ->label('Collection cycle')
                        ->options(fn () => CollectionCycle::orderByDesc('id')->pluck('name', 'id'))
                        ->searchable()->required()->columnSpanFull()
                        ->rules(fn (Member $record) => [
                            Rule::exists('collection_cycles', 'id'),
                            Rule::unique('payments', 'collection_cycle_id')->where('member_id', $record->id),
                        ])
                        ->validationMessages(['unique' => 'This member already has a payment for this collection cycle. Edit the existing payment instead.']),
                    TextInput::make('amount')->numeric()->prefix('₱')->minValue(0)->default(0)->required(),
                    DatePicker::make('date_paid')->default(now()),
                    TextInput::make('receipt_number')->required()->maxLength(255)->columnSpanFull(),
                    Textarea::make('notes')->required()->rows(3)->columnSpanFull(),
                    Actions::make([
                        $action->getModalSubmitAction(),
                        $action->getModalCancelAction(),
                    ])->columnSpanFull(),
                    ]),
                    Section::make('Payment history')->schema([
                        View::make('filament.record-payment-history')
                            ->viewData(fn (Member $record) => [
                                'member' => $record,
                                'payments' => $record->payments()->with('collectionCycle')->orderByDesc('date_paid')->orderByDesc('id')->get(),
                            ]),
                    ]),
                ]),
            ])
            ->action(function (Member $record, array $data): void {
                $record->payments()->create($data);
                Notification::make()->title('Payment recorded')->success()->send();
            });
    }
}
