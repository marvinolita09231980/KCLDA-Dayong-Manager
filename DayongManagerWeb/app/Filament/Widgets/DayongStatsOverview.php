<?php

namespace App\Filament\Widgets;

use App\Models\BankTransaction;
use App\Models\CollectionCycle;
use App\Models\Disbursement;
use App\Models\Member;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DayongStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $collections = (float) Payment::sum('amount');
        $expenses = (float) Disbursement::sum('amount');
        $deposits = (float) BankTransaction::where('transaction_type', 'Deposit')->sum('amount');
        $withdrawals = (float) BankTransaction::where('transaction_type', 'Withdrawal')->sum('amount');
        $peso = "\u{20B1}";
        $position = app(\App\Services\DayongFinancialPeriod::class)->position();

        return [
            Stat::make('Active members', Member::living()->where('member_status', 'Active')->count())->icon('heroicon-o-users')->description('Brothers and associates in our care'),
            Stat::make('Total collections', $peso.number_format($collections, 2))->icon('heroicon-o-banknotes')->description('All recorded member payments')->color('success'),
            Stat::make('Disbursements', $peso.number_format($expenses, 2))->icon('heroicon-o-arrow-up-tray')->description('Benefits and community expenses')->color('danger'),
            Stat::make('Dayong cash balance', $peso.number_format($position['currentDayongBalanceCents'] / 100, 2))->icon('heroicon-o-banknotes')->description('Funds less paid disbursements'),
            Stat::make('Outstanding payables', $peso.number_format($position['outstandingPayablesCents'] / 100, 2))->icon('heroicon-o-clock')->description('Unpaid commitments'),
            Stat::make('Available Dayong fund', $peso.number_format($position['availableBalanceCents'] / 100, 2))->icon('heroicon-o-heart')->description('Cash balance less outstanding payables')->color('primary'),
            Stat::make('Bank ledger balance', $peso.number_format($deposits - $withdrawals, 2))->icon('heroicon-o-building-library')->description('Deposits less withdrawals'),
            Stat::make('Active collection cycles', CollectionCycle::where('active', true)->count())->icon('heroicon-o-calendar-days')->description('Current community collections'),
        ];
    }
}
