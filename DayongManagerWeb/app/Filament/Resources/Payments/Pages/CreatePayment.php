<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function afterFill(): void
    {
        $memberId = request()->integer('member_id');
        if ($memberId && \App\Models\Member::whereKey($memberId)->exists()) {
            $this->data['member_id'] = $memberId;
        }
    }
}
