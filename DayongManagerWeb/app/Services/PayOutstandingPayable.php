<?php

namespace App\Services;

use App\Models\Disbursement;
use App\Models\Payable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PayOutstandingPayable
{
    public function pay(Payable $payable, array $data, User $user): Disbursement
    {
        abort_unless($user->hasPermission('disbursements.view') && $user->hasPermission('disbursements.create')
            && $user->hasPermission('disbursements.edit'), 403);

        $data = Validator::make($data, [
            'disbursement_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'voucher_number' => ['required', 'string', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($payable, $data, $user): Disbursement {
            $payable = Payable::query()->lockForUpdate()->findOrFail($payable->id);
            if ($payable->disbursement_id) {
                return $payable->disbursement;
            }

            $closure = app(DayongFinancialPeriod::class)->currentClosure();
            if ($closure && $data['disbursement_date'] <= $closure->disbursement_date->toDateString()) {
                throw ValidationException::withMessages(['disbursement_date' => 'Choose a payment date after the last closed financial period.']);
            }

            $disbursement = Disbursement::create($data + [
                'payee' => $payable->payee,
                'category' => $payable->category,
                'particulars' => $payable->particulars,
                'amount' => $payable->amount,
                'recorded_by' => $user->name,
            ]);
            // Conditional update also guards against concurrent submissions on SQLite.
            $updated = Payable::whereKey($payable->id)->whereNull('disbursement_id')
                ->update(['disbursement_id' => $disbursement->id, 'updated_at' => now()]);
            if (! $updated) {
                throw ValidationException::withMessages(['disbursement_date' => 'This payable has already been paid. Refresh the list.']);
            }

            return $disbursement;
        }, 3);
    }
}
