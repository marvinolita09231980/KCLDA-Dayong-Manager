<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class WelcomeOverview extends Widget
{
    protected static ?int $sort = -10;
    protected static bool $isLazy = false;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.widgets.welcome-overview';
}
