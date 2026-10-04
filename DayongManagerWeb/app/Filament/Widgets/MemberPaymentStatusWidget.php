<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Member;
use App\Models\Payment;
use App\Services\PaymentReportExport;
use App\Services\PaymentCycleColumns;
use App\Services\MemberObligationReminder;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MemberPaymentStatusWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public array $sharedFilters = [];

    public static function canView(): bool
    {
        return PaymentResource::canViewAny();
    }

    public function mount(array $sharedFilters = []): void
    {
        abort_unless(static::canView(), 403);

        $this->sharedFilters = $sharedFilters;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->membersQuery())
            ->heading('Unpaid members')
            ->headerActions([
                PaymentReportExport::action(true, fn () => $this->getFilteredTableQuery(), fn () => $this->sharedFilters),
            ])
            ->description('Showing members missing a positive payment in at least one applicable cycle and date range. Registration and annual dues for the same year count as one obligation.')
            ->columns([
                TextColumn::make('row_number')->label('#')->rowIndex(),
                TextColumn::make('full_name')
                    ->label('Member')
                    ->state(fn (Member $record) => $record->full_name)
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('council')->label('Council')->sortable(),
                TextColumn::make('cycle_status')
                    ->label('Selected cycles')
                    ->state(fn (Member $record) => $this->cycleStatus($record))
                    ->wrap(),
                TextColumn::make('amount_paid')
                    ->label('Amount paid')
                    ->state(fn (Member $record) => '₱'.number_format($this->amountPaid($record), 2)),
            ])
            ->recordActions([
                Action::make('obligationReminder')
                    ->label('Message dues')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn () => PaymentResource::canViewAny())
                    ->modalHeading(fn (Member $record) => 'Due obligations — '.$record->full_name)
                    ->modalContent(function (Member $record) {
                        abort_unless(PaymentResource::canViewAny(), 403);

                        return view('filament.member-obligation-reminder', app(MemberObligationReminder::class)->prepare($record, $this->sharedFilters));
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('editExistingPayment')
                    ->label('Review payment')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Member $record) => ($payment = $this->existingUnpaidPayment($record))
                        ? PaymentResource::getUrl('edit', ['record' => $payment->id]) : null)
                    ->visible(fn (Member $record) => PaymentResource::canEdit(null) && $this->existingUnpaidPayment($record) !== null),
                Action::make('recordPayment')
                    ->label('Record payment')
                    ->icon('heroicon-o-banknotes')
                    ->url(fn (Member $record) => PaymentResource::getUrl('create', [
                        'member_id' => $record->id,
                        'collection_cycle_id' => $this->firstUnpaidCycleId($record),
                    ]))
                    ->visible(fn (Member $record) => PaymentResource::canCreate() && $this->existingUnpaidPayment($record) === null),
            ])
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No unpaid members for these filters');
    }

    private function selectedColumns(): Collection
    {
        return PaymentCycleColumns::selected($this->sharedFilters);
    }

    private function paymentQuery(Builder $query, array $cycleIds): Builder
    {
        $dates = $this->sharedFilters['payment_date'] ?? [];

        return $query->whereIn('collection_cycle_id', $cycleIds)
            ->where('amount', '>', 0)
            ->when($dates['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('date_paid', '>=', $from))
            ->when($dates['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('date_paid', '<=', $to));
    }

    private function membersQuery(): Builder
    {
        $columns = $this->selectedColumns();
        $query = Member::living();

        $query->when($this->sharedFilters['council']['value'] ?? null,
            fn (Builder $query, string $council) => $query->where('council', $council));

        if ($columns->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $query->where(function (Builder $query) use ($columns): void {
            foreach ($columns as $column) {
                foreach ($column['cycles'] as $cycle) {
                    $query->orWhere(fn (Builder $query) => PaymentCycleColumns::whereCycleApplicable($query, $cycle)
                        ->whereDoesntHave('payments', fn (Builder $query) => $this->paymentQuery($query, [$cycle->id])));
                }
            }
        });

        return $query->with(['payments' => fn ($query) => $query
            ->whereIn('collection_cycle_id', $columns->flatMap(fn ($column) => $column['ids'])->all())
            ->when($this->sharedFilters['payment_date']['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('date_paid', '>=', $from))
            ->when($this->sharedFilters['payment_date']['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('date_paid', '<=', $to))]);
    }

    private function cycleStatus(Member $member): string
    {
        $unpaid = $this->unpaidColumns($member)->pluck('name');

        return $unpaid->isEmpty() ? 'All selected cycles paid' : 'Unpaid: '.$unpaid->join(', ');
    }

    private function amountPaid(Member $member): float
    {
        return $this->selectedColumns()->filter(fn ($column) => PaymentCycleColumns::applicable($member, $column))
            ->sum(fn ($column) => PaymentCycleColumns::cents($member, $column)) / 100;
    }

    private function unpaidColumns(Member $member): Collection
    {
        return $this->selectedColumns()
            ->filter(fn ($column) => PaymentCycleColumns::applicable($member, $column))
            ->filter(fn ($column) => PaymentCycleColumns::creditedCents($member, $column) === 0);
    }

    private function firstUnpaidCycleId(Member $member): ?int
    {
        $column = $this->unpaidColumns($member)->first();
        if (! $column) {
            return null;
        }
        return PaymentCycleColumns::charge($member, $column)?->id;
    }

    private function existingUnpaidPayment(Member $member): ?Payment
    {
        return $member->payments()->whereIn('collection_cycle_id', $this->unpaidColumns($member)->map(fn ($column) => PaymentCycleColumns::charge($member, $column)?->id)->filter()->all())
            ->orderBy('collection_cycle_id')->first();
    }
}
