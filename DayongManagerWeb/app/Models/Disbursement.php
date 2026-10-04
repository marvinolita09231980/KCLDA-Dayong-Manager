<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disbursement extends Model
{
    protected $guarded = [];

    public function payable(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Payable::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            if ($record->isDirty(['amount', 'disbursement_date', 'payee', 'category', 'particulars', 'voucher_number']) && $record->payable()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'This disbursement settles a payable and cannot be changed.']);
            }
        });
        static::deleting(function (self $record): void {
            if ($record->payable()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'This disbursement settles a payable and cannot be deleted.']);
            }
        });
    }
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'disbursement_date' => 'date',
            'closes_financial_period' => 'boolean',
            'closing_balance' => 'decimal:2',
            'period_closed_at' => 'datetime',
        ];
    }
}
