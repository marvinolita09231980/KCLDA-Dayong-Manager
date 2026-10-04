<?php

namespace App\Filament\Pages;

use App\Services\CollectionStatisticsPdf;
use App\Services\CollectionStatisticsReport;
use App\Services\DayongFinancialPeriod;
use App\Services\PaymentCycleColumns;
use App\Services\YearRangeFinancialReport;
use App\Models\CollectionCycle;
use App\Models\Disbursement;
use App\Models\Payable;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CollectionStatistics extends Page implements HasTable
{
    use InteractsWithTable;

    public ?string $startYear = null;

    public ?string $endYear = null;

    private ?array $yearOptionsCache = null;

    protected static ?string $title = 'Collection Statistics';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.collection-statistics';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('collections.view');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadReport')
                ->label('Download report')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => app(CollectionStatisticsPdf::class)->download($this->downloadData())),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->statistics()['rows']->all())
            ->heading('Collections per council')
            ->columns([
                View::make('filament.tables.council-cycle-statistics'),
            ])
            ->filters([
                SelectFilter::make('cycles')
                    ->label('Collection cycles')
                    ->options(fn () => PaymentCycleColumns::all()->sortByDesc('id')->pluck('name', 'id')->all())
                    ->default([])
                    ->multiple()->searchable()->preload(),
                Filter::make('payment_date')
                    ->label('Payment date')
                    ->schema([
                        DatePicker::make('from')->label('Paid from')->maxDate(fn (Get $get) => $get('to')),
                        DatePicker::make('to')->label('Paid through')->minDate(fn (Get $get) => $get('from')),
                    ])
                    ->columns(2)
                    ->indicateUsing(fn (array $data): array => array_filter([
                        filled($data['from'] ?? null) ? 'Paid from '.$data['from'] : null,
                        filled($data['to'] ?? null) ? 'Paid through '.$data['to'] : null,
                    ])),
            ])
            ->deferFilters(false)
            ->emptyStateHeading('No members yet')
            ->paginated(false);
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        $canViewFinancialPosition = $this->canViewFinancialPosition();
        $rangeActive = filled($this->startYear) || filled($this->endYear);
        [$firstYear, $lastYear] = $this->selectedYears();

        return $this->statistics() + [
            'yearOptions' => $this->yearOptions(),
            'yearRangeActive' => $rangeActive,
            'yearRangeLabel' => $this->yearRangeLabel(),
            'canViewFinancialPosition' => $canViewFinancialPosition,
            'yearFinancial' => $canViewFinancialPosition && $rangeActive ? app(YearRangeFinancialReport::class)->forYears($firstYear, $lastYear) : null,
            'financialPosition' => $canViewFinancialPosition && ! $rangeActive ? app(DayongFinancialPeriod::class)->position() : null,
        ];
    }

    public function updatedStartYear(): void
    {
        if (filled($this->startYear) && filled($this->endYear) && (int) $this->startYear > (int) $this->endYear) {
            $this->endYear = $this->startYear;
        }
    }

    public function updatedEndYear(): void
    {
        if (filled($this->startYear) && filled($this->endYear) && (int) $this->endYear < (int) $this->startYear) {
            $this->startYear = $this->endYear;
        }
    }

    public function clearYearRange(): void
    {
        $this->startYear = null;
        $this->endYear = null;
    }

    private function yearOptions(): array
    {
        if ($this->yearOptionsCache !== null) {
            return $this->yearOptionsCache;
        }

        $years = [now()->year];
        foreach (CollectionCycle::all() as $cycle) {
            if ($year = PaymentCycleColumns::year($cycle)) {
                $years[] = $year;
            }
        }
        foreach ([
            Payment::min('date_paid'), Payment::max('date_paid'), Payment::min('created_at'), Payment::max('created_at'),
            Disbursement::min('disbursement_date'), Disbursement::max('disbursement_date'),
            Payable::min('created_at'), Payable::max('created_at'),
        ] as $date) {
            if ($date) {
                $years[] = \Illuminate\Support\Carbon::parse($date)->year;
            }
        }

        $years = array_filter($years, fn (int $year) => $year <= now()->year);
        $range = range(min($years), max($years));

        return $this->yearOptionsCache = array_combine($range, $range);
    }

    private function canViewFinancialPosition(): bool
    {
        return auth()->user()->hasPermission('ledger.view')
            && auth()->user()->hasPermission('disbursements.view');
    }

    private function downloadData(): array
    {
        $statistics = $this->statistics();
        $dates = $this->getTableFilterState('payment_date') ?? [];
        $cycles = $statistics['cycleSummaries']->pluck('name')->join(', ');
        $rangeActive = filled($this->startYear) || filled($this->endYear);
        [$firstYear, $lastYear] = $this->selectedYears();
        if ($rangeActive) {
            $paymentDates = max($dates['from'] ?? sprintf('%04d-01-01', $firstYear), sprintf('%04d-01-01', $firstYear))
                .' to '.min($dates['to'] ?? sprintf('%04d-12-31', $lastYear), sprintf('%04d-12-31', $lastYear));
        } else {
            $paymentDates = ($dates['from'] ?? null) || ($dates['to'] ?? null)
                ? (($dates['from'] ?? 'Beginning').' — '.($dates['to'] ?? 'Present'))
                : 'All dates';
        }

        return $statistics + [
            'generatedAt' => now()->format('M j, Y g:i A'),
            'filterCycles' => $cycles ?: 'No matching cycles',
            'yearRange' => $this->yearRangeLabel(),
            'paymentDates' => $paymentDates,
            'yearFinancial' => $this->canViewFinancialPosition() && $rangeActive ? app(YearRangeFinancialReport::class)->forYears($firstYear, $lastYear) : null,
            'financialPosition' => $this->canViewFinancialPosition() && ! $rangeActive ? app(DayongFinancialPeriod::class)->position() : null,
        ];
    }

    private array $statisticsCache = [];

    private function selectedYears(): array
    {
        $options = $this->yearOptions();
        $years = array_keys($options);
        $first = filled($this->startYear) && isset($options[(int) $this->startYear]) ? (int) $this->startYear : min($years);
        $last = filled($this->endYear) && isset($options[(int) $this->endYear]) ? (int) $this->endYear : max($years);

        return [$first, max($first, $last)];
    }

    private function yearRangeLabel(): ?string
    {
        if (blank($this->startYear) && blank($this->endYear)) {
            return null;
        }

        [$first, $last] = $this->selectedYears();

        return $first === $last ? (string) $first : $first.' to '.$last;
    }

    private function statistics(): array
    {
        $cycleIds = $this->getTableFilterState('cycles')['values'] ?? [];
        $dates = $this->getTableFilterState('payment_date') ?? [];
        $key = json_encode([$cycleIds, $dates, $this->startYear, $this->endYear]);
        if (isset($this->statisticsCache[$key])) {
            return $this->statisticsCache[$key];
        }
        $rangeActive = filled($this->startYear) || filled($this->endYear);
        [$firstYear, $lastYear] = $this->selectedYears();
        $columns = PaymentCycleColumns::selected(['collection_cycle_id' => ['values' => $cycleIds]])
            ->filter(function ($column) use ($rangeActive, $firstYear, $lastYear) {
                $year = $column['year'] ?? PaymentCycleColumns::year($column['cycles']->first());

                return ! $rangeActive || ($year && $year >= $firstYear && $year <= $lastYear);
            })
            ->sortByDesc('id')->values();
        if ($rangeActive) {
            $dates['from'] = max($dates['from'] ?? sprintf('%04d-01-01', $firstYear), sprintf('%04d-01-01', $firstYear));
            $dates['to'] = min($dates['to'] ?? sprintf('%04d-12-31', $lastYear), sprintf('%04d-12-31', $lastYear));
        }
        $selectedCycleIds = $columns->flatMap(fn ($column) => $column['ids'])->all();
        // The report service treats an empty ID list as "all cycles". Here, an empty
        // selection means the page filters matched no cycles.
        $report = app(CollectionStatisticsReport::class)->generate(
            $selectedCycleIds ?: [0], $dates['from'] ?? null, $dates['to'] ?? null,
        );
        $summaries = $columns->map(function ($column) use ($dates) {
            $cycleReport = app(CollectionStatisticsReport::class)->generate($column['ids'], $dates['from'] ?? null, $dates['to'] ?? null);

            return ['id' => $column['id'], 'name' => $column['name'], 'totals' => $cycleReport['totals'], 'rows' => $cycleReport['rows']];
        });
        $report['rows'] = $report['rows']->map(function ($row) use ($summaries) {
            $row['cycles'] = $summaries->map(function ($summary) use ($row) {
                $counts = $summary['rows']->first(fn ($counts) => $counts['council'] === $row['council'])
                    ?? ['paid' => 0, 'unpaid' => 0, 'payment_records' => 0, 'zero_payment_records' => 0, 'amount_cents' => 0];

                return ['id' => $summary['id'], 'name' => $summary['name']] + $counts;
            })->all();

            return $row;
        });

        return $this->statisticsCache[$key] = $report + ['cycleSummaries' => $summaries];
    }
}
