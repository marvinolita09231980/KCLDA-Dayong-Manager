<?php

namespace App\Filament\Pages;

use App\Services\ComplianceReport;
use App\Services\ComplianceCouncilPdf;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Compliance extends Page
{
    protected static ?string $title = 'Good Standing & Compliance';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Membership';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.compliance';

    public string $search = '';

    public string $council = '';

    public string $recommendation = '';

    public function changeStatusAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('changeStatus')
            ->label('Change status')
            ->modalHeading(fn (array $arguments) => 'Change status — '.\App\Models\Member::findOrFail($arguments['member'])->full_name)
            ->modalSubmitActionLabel('Save status')
            ->visible(fn () => static::canAccess() && auth()->user()->hasPermission('members.edit'))
            ->schema([
                \Filament\Forms\Components\Select::make('member_status')
                    ->label('Member status')->options(['Active' => 'Active', 'Inactive' => 'Inactive', 'Expelled' => 'Expelled', 'Deceased' => 'Deceased'])->required()
                    ->in(['Active', 'Inactive', 'Expelled', 'Deceased']),
            ])
            ->fillForm(function (array $arguments): array {
                abort_unless(static::canAccess() && auth()->user()->hasPermission('members.edit'), 403);

                return ['member_status' => \App\Models\Member::findOrFail($arguments['member'])->member_status];
            })
            ->action(function (array $data, array $arguments): void {
                abort_unless(static::canAccess() && auth()->user()->hasPermission('members.edit'), 403);
                $member = \App\Models\Member::findOrFail($arguments['member']);
                $member->update(['member_status' => $data['member_status']]);
                \Filament\Notifications\Notification::make()->title('Member status updated')->success()->send();
            });
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'council', 'recommendation');
    }

    public function downloadCouncilReport(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        abort_unless($this->council !== '', 422);

        return app(ComplianceCouncilPdf::class)->download($this->council);
    }

    public function downloadAllCouncilReports(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        return app(ComplianceCouncilPdf::class)->downloadAll();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->active && auth()->user()->hasPermission('compliance.view');
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $rows = app(ComplianceReport::class)->rows();

        return ['councils' => $rows->pluck('council')->unique(), 'rows' => $rows->filter(fn ($row) => ($this->search === '' || str_contains(mb_strtolower($row['name']), mb_strtolower($this->search))) &&
            ($this->council === '' || $row['council'] === $this->council) &&
            (match ($this->recommendation) {
                '' => true,
                'Needs review' => in_array($row['recommendation'], ['Review for Inactive status', 'Subject for Board expulsion review'], true),
                'Not in good standing - but not subject for review' => $row['standing'] === 'No' && ! in_array($row['recommendation'], ['Review for Inactive status', 'Subject for Board expulsion review'], true),
                default => $row['recommendation'] === $this->recommendation,
            }))];
    }
}
