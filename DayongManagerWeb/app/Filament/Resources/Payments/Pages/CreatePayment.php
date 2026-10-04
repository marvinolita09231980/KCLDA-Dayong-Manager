<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\CollectionCycle;
use App\Models\Member;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\WithPagination;

class CreatePayment extends CreateRecord
{
    use WithPagination;

    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['notes'] = $data['notes'] ?? '';

        return $data;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 2])->components([
            Section::make('Payment details')->schema([$this->getFormContentComponent()]),
            Section::make('Member payment history')->schema([
                View::make('filament.pages.create-payment-history'),
            ]),
        ]);
    }

    public function updatedDataMemberId(): void
    {
        $this->resetPage('paymentHistoryPage');
    }

    public function getPaymentHistoryMember(): ?Member
    {
        return Member::find($this->data['member_id'] ?? null);
    }

    protected function afterFill(): void
    {
        $memberId = request()->integer('member_id');
        if ($memberId && Member::whereKey($memberId)->exists()) {
            $this->data['member_id'] = $memberId;
        }

        $cycleId = request()->integer('collection_cycle_id');
        if ($cycleId && CollectionCycle::whereKey($cycleId)->exists()) {
            $this->data['collection_cycle_id'] = $cycleId;
        }
    }
}
