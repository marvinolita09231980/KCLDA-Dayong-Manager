<?php

namespace App\Filament\Widgets;

use App\Models\BankTransaction;
use App\Models\Disbursement;
use App\Models\Member;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DayongStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $collections = (float) Payment::sum('amount');
        $expenses = (float) Disbursement::sum('amount');
        $deposits = (float) BankTransaction::where('transaction_type', 'Deposit')->sum('amount');
        $withdrawals = (float) BankTransaction::where('transaction_type', 'Withdrawal')->sum('amount');

        return [
            Stat::make('Active members', Member::where('member_status', 'Active')->count())->icon('heroicon-o-users'),
            Stat::make('Total collections', '₱'.number_format($collections, 2))->color('success'),
            Stat::make('Disbursements', '₱'.number_format($expenses, 2))->color('danger'),
            Stat::make('Available Dayong fund', '₱'.number_format($collections - $expenses, 2))->color('primary'),
            Stat::make('Bank ledger balance', '₱'.number_format($deposits - $withdrawals, 2))->description('Deposits less withdrawals'),
        ];
    }
}
