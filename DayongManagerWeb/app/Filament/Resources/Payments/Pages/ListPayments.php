<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Widgets\MemberPaymentStatusWidget;
use App\Services\PaymentReportExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public bool $receiptEditing = false;

    public function getTabs(): array
    {
        return [
            'paid' => Tab::make('Paid')->modifyQueryUsing(fn (Builder $query) => $query
                ->where('amount', '>', 0)
                ->where(fn (Builder $query) => $query
                    ->whereHas('collectionCycle', fn (Builder $cycle) => $cycle->whereIn('type', ['Registration Fee', 'Annual Dues']))
                    ->orWhereHas('member', fn (Builder $member) => $member
                        ->whereNull('start_cycle_id')
                        ->orWhereColumn('start_cycle_id', '<=', 'payments.collection_cycle_id')))),
            'unpaid' => Tab::make('Unpaid')->modifyQueryUsing(fn (Builder $query) => $query->whereRaw('1 = 0')),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.payment-shared-filters')
                ->viewData(fn (): array => ['filtersForm' => $this->getTableFiltersForm()]),
            $this->getTabsContentComponent(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE)
                ->visible(fn () => $this->activeTab === 'paid'),
            EmbeddedTable::make()->visible(fn () => $this->activeTab === 'paid'),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER)
                ->visible(fn () => $this->activeTab === 'paid'),
            Livewire::make(MemberPaymentStatusWidget::class, fn (): array => [
                'sharedFilters' => $this->tableFilters ?? [],
            ])
                ->key(fn (): string => 'unpaid-members-'.md5(json_encode($this->tableFilters ?? [])))
                ->visible(fn () => $this->activeTab === 'unpaid'),
        ]);
    }

    public function toggleReceiptEditing(): void
    {
        abort_unless(PaymentResource::canEdit(null), 403);

        $this->receiptEditing = ! $this->receiptEditing;
    }

    protected function getHeaderActions(): array
    {
        return [
            PaymentReportExport::action(false, fn () => $this->getFilteredTableQuery(), fn () => $this->tableFilters ?? [])
                ->visible(fn (): bool => $this->activeTab === 'paid' && PaymentResource::canViewAny()),
            Action::make('toggleReceiptEditing')
                ->label(fn (): string => $this->receiptEditing ? 'Lock receipt numbers' : 'Edit receipt numbers')
                ->icon(fn (): string => $this->receiptEditing ? 'heroicon-o-lock-closed' : 'heroicon-o-pencil-square')
                ->color(fn (): string => $this->receiptEditing ? 'gray' : 'primary')
                ->visible(fn (): bool => $this->activeTab === 'paid' && PaymentResource::canEdit(null))
                ->action(fn () => $this->toggleReceiptEditing()),
            CreateAction::make(),
        ];
    }
}
