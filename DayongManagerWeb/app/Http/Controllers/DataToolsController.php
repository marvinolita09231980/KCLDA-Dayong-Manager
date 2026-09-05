<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DataToolsController extends Controller
{
    private const TABLES = ['members', 'collection_cycles', 'payments', 'bank_transactions', 'disbursements'];

    private const MEMBER_FIELDS = ['last_name', 'first_name', 'middle_name', 'council', 'address', 'contact_number', 'beneficiary_name', 'beneficiary_contact', 'membership_type', 'sponsor_name', 'remarks'];

    private function authorizeTool(Request $request, string $permission): void
    {
        abort_unless($request->user()?->active && $request->user()->hasPermission($permission), 403);
    }

    public function export(Request $request)
    {
        $this->authorizeTool($request, 'tools.export');
        $table = $request->validate(['table' => 'required|in:'.implode(',', self::TABLES)])['table'];
        $columns = Schema::getColumnListing($table);

        return response()->streamDownload(function () use ($table, $columns) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $columns);
            foreach (DB::table($table)->orderBy('id')->cursor() as $record) {
                fputcsv($file, array_map(function ($column) use ($record) {
                    $value = $record->$column;

                    return is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
                }, $columns));
            }
            fclose($file);
        }, $table.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function template(Request $request)
    {
        $this->authorizeTool($request, 'tools.import');

        return response(implode(',', self::MEMBER_FIELDS)."\r\n", 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="member-import-template.csv"']);
    }

    public function importMembers(Request $request)
    {
        $this->authorizeTool($request, 'tools.import');
        $request->validate(['file' => 'required|file|max:10240']);
        $file = fopen($request->file('file')->getRealPath(), 'r');
        try {
            $header = fgetcsv($file);
            if (! $header) {
                throw ValidationException::withMessages(['file' => 'The CSV is empty.']);
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            if (array_diff(['last_name', 'first_name', 'council'], $header) || array_diff($header, self::MEMBER_FIELDS) || count($header) !== count(array_unique($header))) {
                throw ValidationException::withMessages(['file' => 'Use the member import template with unique column headings.']);
            }
            $count = DB::transaction(function () use ($file, $header) {
                $count = 0;
                $line = 1;
                while (($values = fgetcsv($file)) !== false) {
                    $line++;
                    if ($values === [null]) {
                        continue;
                    }
                    if (count($values) !== count($header)) {
                        throw ValidationException::withMessages(['file' => "Invalid column count at row {$line}. No records imported."]);
                    }
                    $record = array_combine($header, array_map(fn ($value) => trim($value ?? ''), $values));
                    $rules = array_fill_keys(self::MEMBER_FIELDS, 'sometimes|nullable|string|max:255');
                    foreach (['first_name', 'last_name', 'council'] as $key) {
                        $rules[$key] = 'required|string|max:255';
                    }
                    $validator = Validator::make($record, $rules);
                    if ($validator->fails()) {
                        throw ValidationException::withMessages(['file' => "Row {$line}: ".$validator->errors()->first().' No records imported.']);
                    }
                    $identity = array_intersect_key($record, array_flip(['last_name', 'first_name', 'council']));
                    $identity['middle_name'] = $record['middle_name'] ?? '';
                    if (Member::where($identity)->exists()) {
                        throw ValidationException::withMessages(['file' => "Duplicate member at row {$line}. No records imported."]);
                    }
                    Member::create($record);
                    $count++;
                }
                if (! $count) {
                    throw ValidationException::withMessages(['file' => 'The CSV contains no members.']);
                }

                return $count;
            });
        } finally {
            fclose($file);
        }

        return back()->with('status', "Imported {$count} members.");
    }

    public function importWindows(Request $request)
    {
        $this->authorizeTool($request, 'tools.import');
        $request->validate(['file' => 'required|file|max:102400']);
        $result = Artisan::call('dayong:import-windows', ['source' => $request->file('file')->getRealPath()]);
        if ($result !== 0) {
            throw ValidationException::withMessages(['file' => trim(Artisan::output())]);
        }

        return back()->with('status', 'Windows database imported successfully. A backup was saved before import.');
    }

    public function backup(Request $request)
    {
        $this->authorizeTool($request, 'tools.backup');
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw ValidationException::withMessages(['backup' => 'Database backup currently supports SQLite only.']);
        }
        $directory = storage_path('app/private/backups');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $path = $directory.'/dayong-'.now()->format('Ymd-His').'-'.Str::random(8).'.sqlite';
        DB::connection()->getPdo()->exec('VACUUM INTO '.DB::connection()->getPdo()->quote($path));

        return response()->download($path, basename($path), ['Content-Type' => 'application/octet-stream']);
    }
}
