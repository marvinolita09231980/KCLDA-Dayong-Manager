<x-filament-panels::page>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if(auth()->user()->hasPermission('tools.import'))
    <x-filament::section heading="Import members from CSV">
        <p>Download the template, fill in member details, then upload it. First name, last name and council are required. This adds new members; duplicate members reject the entire file.</p>
        <p><a style="color:#2563eb" href="{{ route('tools.template') }}">Download member import template</a></p>
        <form method="post" action="{{ route('tools.import-members') }}" enctype="multipart/form-data" style="margin-top:1rem">@csrf
            <input aria-label="Members CSV file" type="file" name="file" accept=".csv" required>
            <x-filament::button type="submit">Import members</x-filament::button>
        </form>
    </x-filament::section>
    <x-filament::section heading="Import Windows database">
        <p>Upload the Windows app's dayong.db file. Your server's upload size limit applies. Imports business records into an empty web database and keeps your web login. Existing members or transactions prevent import. A backup is saved automatically.</p>
        <form method="post" action="{{ route('tools.import-windows') }}" enctype="multipart/form-data" style="margin-top:1rem">@csrf
            <input aria-label="Windows database file" type="file" name="file" accept=".db,.sqlite" required>
            <x-filament::button type="submit">Import Windows database</x-filament::button>
        </form>
    </x-filament::section>
    @endif
    @if(auth()->user()->hasPermission('tools.export'))
    <x-filament::section heading="Export records">
        <p>Download records as CSV for Excel. Exports are reports; use the member template for importing new members.</p>
        <form method="post" action="{{ route('tools.export') }}" style="margin-top:1rem">@csrf
            <label>Records <select name="table">@foreach(['members' => 'Members', 'collection_cycles' => 'Collection cycles', 'payments' => 'Payments', 'bank_transactions' => 'Bank ledger', 'disbursements' => 'Disbursements'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <x-filament::button type="submit">Export CSV</x-filament::button>
        </form>
    </x-filament::section>
    @endif
    @if(auth()->user()->hasPermission('tools.backup'))
    <x-filament::section heading="Back up database">
        <p>Create and download a complete SQLite database snapshot, including records, users and permissions. A copy is retained on the server. Store the downloaded file securely.</p>
        <form method="post" action="{{ route('tools.backup') }}" style="margin-top:1rem">@csrf<x-filament::button type="submit">Back up and download database</x-filament::button></form>
    </x-filament::section>
    @endif
</x-filament-panels::page>
