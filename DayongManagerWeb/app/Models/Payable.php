<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payable extends Model
{
    protected $fillable = ['payee', 'category', 'particulars', 'amount', 'due_date', 'recorded_by'];

    protected static function booted(): void
    {
        $ensureOutstanding = function (self $record): void {
            if (static::whereKey($record->id)->whereNotNull('disbursement_id')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'Paid payables cannot be changed or deleted.']);
            }
        };
        static::updating($ensureOutstanding);
        static::deleting($ensureOutstanding);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_date' => 'date'];
    }

    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(Disbursement::class);
    }

    public static function outstandingCents(): int
    {
        return (int) static::whereNull('disbursement_id')
            ->selectRaw('COALESCE(SUM(ROUND(amount * 100)), 0) as cents')->value('cents');
    }
}
