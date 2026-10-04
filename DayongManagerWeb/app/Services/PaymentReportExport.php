<?php

namespace App\Services;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Member;
use Closure;
use Dompdf\Dompdf;
use Dompdf\Options;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReportExport
{
    public static function action(bool $unpaid, Closure $records, Closure $filters): Action
    {
        return Action::make('downloadReport')
            ->label($unpaid ? 'Download unpaid report' : 'Download payments report')
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn () => PaymentResource::canViewAny())
            ->modalHeading($unpaid ? 'Download unpaid report' : 'Download payments report')
            ->modalDescription('Choose your columns and format, then preview the filtered member list before downloading.')
            ->modalWidth('7xl')
            ->modalSubmitActionLabel('Download report')
            ->steps(fn () => [
                Step::make('Report options')->schema([
                    Select::make('format')->label('Download format')
                        ->options(['csv' => 'CSV (Excel)', 'pdf' => 'PDF', 'png' => 'Images (PNG, 15 members per image)'])->default('csv')->required(),
                    Select::make('cycle_ids')
                        ->label('Cycle columns to include')
                        ->options(PaymentCycleColumns::all()->pluck('name', 'id'))
                        ->default(PaymentCycleColumns::selected($filters())->keys()->all())
                        ->multiple()->searchable()->preload()->required()
                        ->helperText('Choose any cycles without changing the member list. Same-year registration and annual dues share one column. Member and council are always included.'),
                ]),
                Step::make('Preview')->schema([
                    View::make('reports.payment-report-preview')->viewData(fn (Get $get) => app(self::class)->preview($unpaid, $records(), $filters(), $get('cycle_ids') ?? [])),
                ]),
            ])
            ->action(function (array $data, $livewire) use ($unpaid, $records, $filters) {
                if (($data['format'] ?? 'csv') === 'png') {
                    $report = app(self::class)->report($unpaid, $records(), $filters(), $data['cycle_ids'] ?? []);
                    $livewire->dispatch('download-payment-report-images', report: $report);

                    return null;
                }

                return app(self::class)->download($unpaid, $records(), $filters(), $data['cycle_ids'] ?? [], $data['format'] ?? 'csv');
            });
    }

    public function preview(bool $unpaid, Builder $records, array $filters, array $cycleIds): array
    {
        try {
            return ['report' => $this->report($unpaid, $records, $filters, $cycleIds), 'previewError' => null];
        } catch (ValidationException $exception) {
            return ['report' => null, 'previewError' => collect($exception->errors())->flatten()->first()];
        }
    }

    public function report(bool $unpaid, Builder $records, array $filters, array $cycleIds): array
    {
        abort_unless(PaymentResource::canViewAny(), 403);
        $available = PaymentCycleColumns::all();
        Validator::make(['cycle_ids' => $cycleIds], [
            'cycle_ids' => ['required', 'array', 'min:1'],
            'cycle_ids.*' => ['integer', 'distinct', Rule::in($available->keys()->all())],
        ])->validate();
        $cycles = $available->whereIn('id', $cycleIds)->values();
        $dates = $filters['payment_date'] ?? [];
        Validator::make($dates, [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...(filled($dates['from'] ?? null) ? ['after_or_equal:from'] : [])],
        ])->validate();

        $members = $unpaid ? clone $records : Member::query()->whereIn('id', (clone $records)->reorder()->select('payments.member_id'));
        if ($unpaid) {
            $members->living();
        }
        $members->with(['payments' => function ($query) use ($cycles): void {
            $query->whereIn('collection_cycle_id', $cycles->flatMap(fn ($column) => $column['ids'])->all());
        }]);

        $headers = ['Member', 'Council', ...$cycles->map(fn ($column) => $column['name'].' (PHP)')->all(), 'Amount to be collected (PHP)'];
        $rows = [];
        $totals = array_fill_keys($cycles->pluck('id')->all(), 0);
        $totalOutstanding = 0;
        foreach ($members->reorder()->lazyById(500) as $member) {
            $row = [$member->full_name, $member->council];
            $total = 0;
            foreach ($cycles as $cycle) {
                if (! PaymentCycleColumns::applicable($member, $cycle)) {
                    $row[] = 'Not applicable';

                    continue;
                }
                $cents = PaymentCycleColumns::cents($member, $cycle);
                $amount = number_format($cents / 100, 2, '.', '');
                $row[] = $amount;
                $total += PaymentCycleColumns::outstandingCents($member, $cycle);
                $totals[$cycle['id']] += $cents;
            }
            $rows[] = [...$row, number_format($total / 100, 2, '.', '')];
            $totalOutstanding += $total;
        }
        $totalRow = ['Total (PHP)', '', ...array_map(fn ($cents) => number_format($cents / 100, 2, '.', ''), array_values($totals)), number_format($totalOutstanding / 100, 2, '.', '')];

        return [
            'title' => $unpaid ? 'Unpaid Members Report' : 'Payments Report',
            'filename' => ($unpaid ? 'unpaid' : 'payments').'-report-'.now()->format('Y-m-d'),
            'generatedAt' => now()->format('M j, Y g:i A'),
            'council' => $filters['council']['value'] ?? 'All councils',
            'dateFrom' => $dates['from'] ?? null,
            'dateTo' => $dates['to'] ?? null,
            'filterCycles' => PaymentCycleColumns::selected($filters)->pluck('name')->join(', '),
            'headers' => $headers,
            'rows' => $rows,
            'totals' => $totalRow,
        ];
    }

    public function download(bool $unpaid, Builder $records, array $filters, array $cycleIds, string $format = 'csv'): StreamedResponse
    {
        abort_unless(PaymentResource::canViewAny(), 403);
        Validator::make(['format' => $format], ['format' => ['required', Rule::in(['csv', 'pdf'])]])->validate();
        $report = $this->report($unpaid, $records, $filters, $cycleIds);

        if ($format === 'pdf') {
            $options = new Options;
            $options->set('isRemoteEnabled', false);
            $options->set('isPhpEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $pdf = new Dompdf($options);
            $pdf->setPaper([0, 0, 612, 936], 'portrait');
            $pdf->loadHtml(view('reports.payment-report-pdf', compact('report'))->render(), 'UTF-8');
            $pdf->render();
            $pdf->getCanvas()->page_text(490, 910, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8, [0.35, 0.35, 0.35]);

            return response()->streamDownload(fn () => print ($pdf->output()), $report['filename'].'.pdf', ['Content-Type' => 'application/pdf']);
        }

        return response()->streamDownload(function () use ($report): void {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            foreach ([$report['headers'], ...$report['rows'], $report['totals']] as $row) {
                fputcsv($file, array_map(fn ($value) => $this->safeCell((string) $value), $row), ',', '"', '');
            }
            fclose($file);
        }, $report['filename'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[\s\x{FEFF}]*[=+@-]/u', $value) ? "'".$value : $value;
    }
}
