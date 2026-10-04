<?php

namespace App\Services;

use App\Models\CollectionCycle;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PaymentCycleColumns
{
    public static function all(): Collection
    {
        return CollectionCycle::orderBy('id')->get()->groupBy(function ($cycle) {
            $year = self::year($cycle);

            return in_array($cycle->type, ['Registration Fee', 'Annual Dues'], true) && $year
                ? 'annual-'.$year : 'cycle-'.$cycle->id;
        })->map(function ($cycles) {
            $first = $cycles->first();
            $year = in_array($first->type, ['Registration Fee', 'Annual Dues'], true) ? self::year($first) : null;

            return [
                'id' => $first->id,
                'name' => $year ? $year.' Annual Dues / Registration' : $first->name,
                'year' => $year,
                'cycles' => $cycles,
                'ids' => $cycles->modelKeys(),
            ];
        })->keyBy('id');
    }

    public static function selected(array $filters): Collection
    {
        $state = $filters['collection_cycle_id'] ?? [];
        $ids = $state['values'] ?? (filled($state['value'] ?? null) ? [$state['value']] : []);

        return self::all()->filter(fn ($column) => $ids === [] || array_intersect($ids, $column['ids']) !== []);
    }

    public static function year(CollectionCycle $cycle): ?int
    {
        if (preg_match('/\b(20\d{2})\b/', $cycle->name, $match)) {
            return (int) $match[1];
        }

        return $cycle->start_date?->year ?? $cycle->due_date?->year;
    }

    public static function applicable(Member $member, array $column): bool
    {
        return self::charge($member, $column) !== null;
    }

    public static function registrationYear(Member $member): ?int
    {
        if ($member->registration_date) {
            return $member->registration_date->year;
        }
        $member->loadMissing('registrationPayments.collectionCycle');

        return $member->registrationPayments->map(fn ($payment) => self::year($payment->collectionCycle))->filter()->min();
    }

    public static function cycleApplicable(Member $member, CollectionCycle $cycle): bool
    {
        $year = self::year($cycle);
        if ($year && in_array($cycle->type, ['Registration Fee', 'Annual Dues'], true)) {
            $registered = self::registrationYear($member);

            return $cycle->type === 'Registration Fee'
                ? $registered === $year
                : ($registered === null || $registered < $year);
        }

        return ! $member->start_cycle_id || $member->start_cycle_id <= $cycle->id;
    }

    public static function charge(Member $member, array $column): ?CollectionCycle
    {
        return $column['cycles']->first(fn ($cycle) => self::cycleApplicable($member, $cycle));
    }

    public static function creditedCents(Member $member, array $column): int
    {
        $ids = $column['cycles']->filter(fn ($cycle) => self::cycleApplicable($member, $cycle))->modelKeys();

        return (int) $member->payments->whereIn('collection_cycle_id', $ids)
            ->sum(fn ($payment) => (int) round((float) $payment->amount * 100));
    }

    public static function whereCycleApplicable(Builder $query, CollectionCycle $cycle): Builder
    {
        $year = self::year($cycle);
        if (! $year || ! in_array($cycle->type, ['Registration Fee', 'Annual Dues'], true)) {
            return $query->where(fn ($query) => $query->whereNull('start_cycle_id')->orWhere('start_cycle_id', '<=', $cycle->id));
        }
        $registration = CollectionCycle::where('type', 'Registration Fee')->get();
        $same = $registration->filter(fn ($item) => self::year($item) === $year)->modelKeys();
        $before = $registration->filter(fn ($item) => self::year($item) && self::year($item) < $year)->modelKeys();
        $dated = $registration->filter(fn ($item) => self::year($item) !== null)->modelKeys();

        return $query->where(function ($query) use ($cycle, $year, $same, $before, $dated) {
            if ($cycle->type === 'Registration Fee') {
                $query->whereYear('registration_date', $year)->orWhere(fn ($query) => $query->whereNull('registration_date')
                    ->whereHas('registrationPayments', fn ($query) => $query->whereIn('collection_cycle_id', $same))
                    ->whereDoesntHave('registrationPayments', fn ($query) => $query->whereIn('collection_cycle_id', $before)));
            } else {
                $query->whereDate('registration_date', '<', $year.'-01-01')->orWhere(fn ($query) => $query->whereNull('registration_date')
                    ->where(fn ($query) => $query->whereDoesntHave('registrationPayments', fn ($query) => $query->whereIn('collection_cycle_id', $dated))
                        ->orWhereHas('registrationPayments', fn ($query) => $query->whereIn('collection_cycle_id', $before))));
            }
        });
    }

    public static function whereApplicable(Builder $query, array $column): Builder
    {
        return $query->where(function (Builder $query) use ($column): void {
            foreach ($column['cycles'] as $cycle) {
                $query->orWhere(fn ($query) => self::whereCycleApplicable($query, $cycle));
            }
        });
    }

    public static function cents(Member $member, array $column): int
    {
        return (int) $member->payments->whereIn('collection_cycle_id', $column['ids'])
            ->sum(fn ($payment) => (int) round((float) $payment->amount * 100));
    }

    public static function outstandingCents(Member $member, array $column): int
    {
        if ($member->isDeceased() || ! self::applicable($member, $column)) {
            return 0;
        }

        $charge = self::charge($member, $column);

        $expected = (int) round((float) $charge->expected_amount * 100);

        return max(0, $expected - self::creditedCents($member, $column));
    }
}
