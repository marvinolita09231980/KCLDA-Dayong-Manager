<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FinancialReport extends Page
{
    protected static ?string $title = 'Financial Report';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.financial-report';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->hasPermission('collections.view') ?? false)
            || (auth()->user()?->hasPermission('disbursements.view') ?? false);
    }

    public function clearDates(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        $validator = Validator::make(
            ['dateFrom' => $this->dateFrom, 'dateTo' => $this->dateTo],
            [
                'dateFrom' => ['nullable', 'date_format:Y-m-d'],
                'dateTo' => ['nullable', 'date_format:Y-m-d', ...($this->dateFrom !== '' ? ['after_or_equal:dateFrom'] : [])],
            ],
            ['dateTo.after_or_equal' => 'Date to must be on or after date from.'],
            ['dateFrom' => 'date from', 'dateTo' => 'date to'],
        );

        $canCollections = auth()->user()->hasPermission('collections.view');
        $canDisbursements = auth()->user()->hasPermission('disbursements.view');
        $collections = collect();
        $disbursements = collect();
        $undatedPayments = 0;
        $dateErrors = $validator->errors()->all();

        if ($dateErrors === []) {
            if ($canCollections) {
                $query = DB::table('payments')->leftJoin('collection_cycles', 'collection_cycles.id', '=', 'payments.collection_cycle_id');
                $this->filterDates($query, 'payments.date_paid');
                $collections = $query->selectRaw("COALESCE(collection_cycles.name, 'Unassigned') as label, COUNT(*) as record_count, SUM(ROUND(payments.amount * 100)) as cents")
                    ->groupBy('collection_cycles.id', 'collection_cycles.name')->orderBy('label')->get();
                $undatedPayments = DB::table('payments')->whereNull('date_paid')->count();
            }

            if ($canDisbursements) {
                $query = DB::table('disbursements');
                $this->filterDates($query, 'disbursement_date');
                $disbursements = $query->selectRaw("COALESCE(NULLIF(TRIM(category), ''), 'Others') as label, COUNT(*) as record_count, SUM(ROUND(amount * 100)) as cents")
                    ->groupByRaw("COALESCE(NULLIF(TRIM(category), ''), 'Others')")->orderBy('label')->get();
            }
        }

        return compact('canCollections', 'canDisbursements', 'collections', 'disbursements', 'undatedPayments', 'dateErrors') + [
            'collectionCents' => (int) $collections->sum('cents'),
            'disbursementCents' => (int) $disbursements->sum('cents'),
            'netCents' => (int) $collections->sum('cents') - (int) $disbursements->sum('cents'),
            'financialPosition' => $canCollections && $canDisbursements ? app(\App\Services\DayongFinancialPeriod::class)->position() : null,
        ];
    }

    private function filterDates(\Illuminate\Database\Query\Builder $query, string $column): void
    {
        $query->when($this->dateFrom !== '', fn ($query) => $query->whereDate($column, '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate($column, '<=', $this->dateTo));
    }
}
