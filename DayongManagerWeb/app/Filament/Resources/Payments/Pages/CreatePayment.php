<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Member;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\WithPagination;

class CreatePayment extends CreateRecord
{
    use WithPagination;

    protected static string $resource = PaymentResource::class;

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
        if ($memberId && \App\Models\Member::whereKey($memberId)->exists()) {
            $this->data['member_id'] = $memberId;
        }
    }
}
