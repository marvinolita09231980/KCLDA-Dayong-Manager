<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectionStatisticsPdf
{
    public function download(array $report): StreamedResponse
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        $pdf = new Dompdf($options);
        $pdf->setPaper([0, 0, 612, 936], 'portrait');
        $pdf->loadHtml($this->html($report), 'UTF-8');
        $pdf->render();
        $pdf->getCanvas()->page_text(490, 914, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 9, [0.35, 0.35, 0.35]);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'collection-statistics-'.now()->format('Y-m-d').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function html(array $report): string
    {
        return view('reports.collection-statistics-pdf', compact('report'))->render();
    }
}
