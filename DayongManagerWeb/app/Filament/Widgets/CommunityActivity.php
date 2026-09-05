<?php

namespace App\Filament\Widgets;

use App\Models\CollectionCycle;
use App\Models\Payment;
use Filament\Widgets\Widget;

class CommunityActivity extends Widget
{
    protected static ?int $sort = 10;
    protected static bool $isLazy = false;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.widgets.community-activity';

    protected function getViewData(): array
    {
        return [
            'payments' => Payment::with(['member', 'collectionCycle'])->orderByDesc('date_paid')->orderByDesc('id')->limit(5)->get(),
            'cycles' => CollectionCycle::where('active', true)->withSum('payments', 'amount')->orderByDesc('id')->limit(4)->get(),
        ];
    }
}
