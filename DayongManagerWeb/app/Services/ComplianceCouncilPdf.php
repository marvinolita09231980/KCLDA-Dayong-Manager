<?php

namespace App\Services;

use App\Filament\Pages\Compliance;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplianceCouncilPdf
{
    public function download(string $council): StreamedResponse
    {
        return $this->downloadReport(
            $this->report($council),
            'good-standing-compliance-council-'.Str::slug($council).'-'.now()->format('Y-m-d').'.pdf',
        );
    }

    public function downloadAll(): StreamedResponse
    {
        return $this->downloadReport(
            $this->reportAll(),
            'good-standing-compliance-all-councils-'.now()->format('Y-m-d').'.pdf',
        );
    }

    private function downloadReport(array $report, string $filename): StreamedResponse
    {

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        $pdf = new Dompdf($options);
        $pdf->setPaper([0, 0, 612, 936], 'portrait');
        $pdf->loadHtml($this->html($report), 'UTF-8');
        $pdf->render();
        $pdf->getCanvas()->page_text(515, 923, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8, [0.35, 0.35, 0.35]);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function report(string $council): array
    {
        abort_unless(Compliance::canAccess(), 403);

        $rows = app(ComplianceReport::class)->rows($council)->values();
        abort_if($rows->isEmpty(), 404, 'No members found for this council.');

        $section = $this->section($council, $rows);

        return $section + [
            'allCouncils' => false,
            'sections' => collect([$section]),
            'councilCount' => 1,
            'asOf' => now(),
        ];
    }

    public function reportAll(): array
    {
        abort_unless(Compliance::canAccess(), 403);

        $rows = app(ComplianceReport::class)->rows();
        abort_if($rows->isEmpty(), 404, 'No members found.');
        $sections = $rows->groupBy(fn (array $row) => filled($row['council']) ? $row['council'] : 'Unassigned')
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn ($members, $council) => $this->section((string) $council, $members->values()))
            ->values();

        return [
            'allCouncils' => true,
            'sections' => $sections,
            'councilCount' => $sections->count(),
            'asOf' => now(),
            'total' => $rows->count(),
            'good' => $rows->where('standing', 'Yes')->count(),
            'notGood' => $rows->where('standing', 'No')->count(),
            'review' => $rows->whereIn('recommendation', ['Review for Inactive status', 'Subject for Board expulsion review'])->count(),
            'deceased' => $rows->where('status', 'Deceased')->count(),
        ];
    }

    private function section(string $council, $rows): array
    {
        return [
            'council' => $council,
            'rows' => $rows,
            'total' => $rows->count(),
            'good' => $rows->where('standing', 'Yes')->count(),
            'notGood' => $rows->where('standing', 'No')->count(),
            'review' => $rows->whereIn('recommendation', ['Review for Inactive status', 'Subject for Board expulsion review'])->count(),
            'deceased' => $rows->where('status', 'Deceased')->count(),
        ];
    }

    public function html(array $report): string
    {
        return view('reports.compliance-council-pdf', compact('report'))->render();
    }
}
