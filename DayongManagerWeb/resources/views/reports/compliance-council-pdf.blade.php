<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Good Standing and Compliance — {{ $report['allCouncils'] ? 'All Councils' : 'Council '.$report['council'] }}</title>
    <style>
        @page { size: 8.5in 13in; margin: 0.25in; }
        body { font-family: Helvetica, Arial, sans-serif; color: #203750; font-size: 10pt; line-height: 1.35; }
        h1 { margin: 0 0 4pt; font-size: 17pt; }
        h2 { margin: 0; font-size: 11pt; }
        p { margin: 3pt 0; }
        .meta { color: #526176; margin-bottom: 12pt; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { vertical-align: top; word-wrap: break-word; }
        .summary { margin: 0 0 12pt; }
        .summary th, .summary td { border: 1px solid #ccd5df; padding: 5pt; text-align: center; }
        .summary th { background: #edf2f7; font-size: 8pt; }
        .summary td { font-size: 13pt; font-weight: bold; }
        .council-heading { margin: 0 0 8pt; padding: 5pt 7pt; background: #244569; color: #fff; font-size: 13pt; }
        .new-page { page-break-before: always; }
        .member { border: 1px solid #ccd5df; margin: 0 0 9pt; page-break-inside: avoid; }
        .member-head { background: #edf2f7; border-bottom: 1px solid #ccd5df; padding: 6pt 8pt; }
        .member-head table td:last-child { text-align: right; width: 36%; }
        .member-body { padding: 5pt 8pt 7pt; }
        .facts th { color: #526176; font-size: 8pt; font-weight: normal; text-align: left; padding: 2pt 4pt 0 0; }
        .facts td { padding: 1pt 4pt 6pt 0; }
        .detail { margin: 4pt 0; }
        .detail strong { color: #142b46; }
        .references { border-top: 1px solid #dbe2ea; margin-top: 6pt; padding-top: 5pt; }
        .note { color: #526176; font-size: 8pt; margin-top: 9pt; }
    </style>
</head>
<body>
    <h1>Good Standing &amp; Compliance</h1>
    <p class="meta">{{ $report['allCouncils'] ? 'All '.$report['councilCount'].' councils' : 'Council '.$report['council'] }} &nbsp;·&nbsp; As of {{ $report['asOf']->format('F j, Y') }}</p>
    @if($report['allCouncils'])
        <table class="summary">
            <thead><tr><th>All members</th><th>Good standing</th><th>Not in good standing</th><th>Needs review</th><th>Deceased</th></tr></thead>
            <tbody><tr><td>{{ $report['total'] }}</td><td>{{ $report['good'] }}</td><td>{{ $report['notGood'] }}</td><td>{{ $report['review'] }}</td><td>{{ $report['deceased'] }}</td></tr></tbody>
        </table>
    @endif

    @foreach($report['sections'] as $section)
    <section class="{{ $loop->first ? 'council-section' : 'council-section new-page' }}">
        <h2 class="council-heading">Council {{ $section['council'] }}</h2>
        <table class="summary">
            <thead><tr><th>Members</th><th>Good standing</th><th>Not in good standing</th><th>Needs review</th><th>Deceased</th></tr></thead>
            <tbody><tr><td>{{ $section['total'] }}</td><td>{{ $section['good'] }}</td><td>{{ $section['notGood'] }}</td><td>{{ $section['review'] }}</td><td>{{ $section['deceased'] }}</td></tr></tbody>
        </table>
    @foreach($section['rows'] as $row)
        <div class="member">
            <div class="member-head">
                <table><tr><td><h2>{{ $row['name'] }}</h2></td><td>{{ $row['status'] }} &nbsp;·&nbsp; {{ $row['standing'] === 'Yes' ? 'Good standing' : ($row['standing'] === 'No' ? 'Not in good standing' : 'Not applicable') }}</td></tr></table>
            </div>
            <div class="member-body">
                <table class="facts">
                    <thead><tr><th>Registered</th><th>Annual dues</th><th>Unpaid mortuary cycles</th><th>Missed latest two</th></tr></thead>
                    <tbody><tr><td>{{ $row['registered'] }}</td><td>{{ $row['annual'] }}<br>Due: PHP {{ number_format($row['annual_due_cents'] / 100, 2) }}</td><td>{{ $row['unpaid'] }}<br>Due: PHP {{ number_format($row['mortuary_due_cents'] / 100, 2) }}</td><td>{{ $row['missed'] }} of 2</td></tr></tbody>
                </table>
                <p class="detail"><strong>Reason:</strong> {{ $row['report_reason'] }}</p>
                <p class="detail"><strong>Recommendation:</strong> {{ $row['recommendation'] ?: 'Not applicable' }}</p>
                <p class="detail references"><strong>Bylaw reference:</strong> {{ implode(', ', $row['bylaw_references']) ?: 'Not applicable' }}
                    @if($row['board_reference_unverified'])
                        ; Board review section to confirm
                    @endif
                </p>
            </div>
        </div>
    @endforeach
    </section>
    @endforeach

    <p class="note">Recommendations support officer and Board review; this report does not change a member's recorded status. Section references are listed without bylaw text.</p>
</body>
</html>
