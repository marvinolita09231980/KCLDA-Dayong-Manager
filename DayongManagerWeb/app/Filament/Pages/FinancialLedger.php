<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Livewire\WithPagination;

class FinancialLedger extends Page
{
    use WithPagination;

    protected static ?string $title = 'Financial Ledger';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.financial-ledger';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearDates(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->active && (
            $user->hasPermission('collections.view')
            || $user->hasPermission('disbursements.view')
            || $user->hasPermission('ledger.view')
        );
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        $user = auth()->user();
        $queries = [];

        if ($user->hasPermission('collections.view')) {
            $queries[] = \DB::table('payments')
                ->join('members', 'members.id', '=', 'payments.member_id')
                ->leftJoin('collection_cycles', 'collection_cycles.id', '=', 'payments.collection_cycle_id')
                ->selectRaw("'Payment' as entry_type, payments.id as record_id, DATE(payments.date_paid) as entry_date, payments.amount as amount, members.first_name as party, members.last_name as secondary, collection_cycles.name as details, payments.receipt_number as reference");
        }

        if ($user->hasPermission('disbursements.view')) {
            $queries[] = \DB::table('disbursements')
                ->selectRaw("'Disbursement' as entry_type, id as record_id, DATE(disbursement_date) as entry_date, amount, payee as party, category as secondary, particulars as details, voucher_number as reference");
        }

        if ($user->hasPermission('ledger.view')) {
            $queries[] = \DB::table('bank_transactions')
                ->selectRaw("'Bank transaction' as entry_type, id as record_id, DATE(transaction_date) as entry_date, amount, transaction_type as party, recorded_by as secondary, description as details, reference_number as reference");
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        $ledger = \DB::query()->fromSub($union, 'ledger');
        $from = $this->validDate($this->dateFrom);
        $to = $this->validDate($this->dateTo);

        if ($from) {
            $ledger->where('entry_date', '>=', $from);
        }
        if ($to) {
            $ledger->where('entry_date', '<=', $to);
        }

        $summary = (clone $ledger)
            ->selectRaw('entry_type, COUNT(*) as record_count, SUM(amount) as total_amount')
            ->groupBy('entry_type')
            ->get()
            ->keyBy('entry_type');

        return [
            'entries' => $ledger->orderByRaw('CASE WHEN entry_date IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('entry_date')->orderByDesc('record_id')->orderBy('entry_type')->paginate(25),
            'summary' => $summary,
            'financialPosition' => $user->hasPermission('collections.view') && $user->hasPermission('disbursements.view')
                ? app(\App\Services\DayongFinancialPeriod::class)->position() : null,
            'invalidRange' => $from && $to && $from > $to,
        ];
    }

    private function validDate(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
