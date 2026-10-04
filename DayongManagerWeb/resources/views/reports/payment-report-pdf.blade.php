<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 28px 28px 42px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #203750; }
        h1 { font-size: 19px; margin: 0 0 8px; }
        p { margin: 4px 0; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 14px; font-size: {{ max(5, min(9, 60 / count($report['headers']))) }}px; }
        th, td { border: 1px solid #ccd5df; padding: 7px 2px; text-align: left; overflow-wrap: anywhere; word-wrap: break-word; }
        th { background: #edf2f7; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .amount { text-align: right; }
        .totals { background: #edf2f7; font-weight: bold; }
    </style>
</head>
<body>
            <h1>KCLDA &mdash; {{ $report['title'] }}</h1>
            <p>Generated {{ $report['generatedAt'] }} &middot; {{ count($report['rows']) }} members &middot; {{ $report['council'] ?: 'All councils' }}</p>
            <p>Member-list cycles: {{ $report['filterCycles'] }}</p>
            @include('reports.payment-report-table', ['indices' => array_keys($report['headers'])])
</body>
</html>
