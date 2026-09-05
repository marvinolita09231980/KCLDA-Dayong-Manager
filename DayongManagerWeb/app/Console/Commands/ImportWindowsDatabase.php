<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;

class ImportWindowsDatabase extends Command
{
    protected $signature = 'dayong:import-windows {source : Full path to the Windows dayong.db} {--dry-run : Validate the import without saving records}';

    protected $description = 'Copy Windows business records into an empty local web database; keep web login accounts';

    private const TABLES = [
        'CollectionCycles' => 'collection_cycles',
        'Members' => 'members',
        'Payments' => 'payments',
        'BankTransactions' => 'bank_transactions',
        'Disbursements' => 'disbursements',
    ];

    public function handle(): int
    {
        try {
            $path = realpath($this->argument('source'));
            if (! $path || ! is_file($path)) {
                throw new RuntimeException('Windows database file not found.');
            }
            if (DB::connection()->getDriverName() !== 'sqlite') {
                throw new RuntimeException('This command supports the local SQLite web database only.');
            }
            if ($path === realpath(DB::connection()->getDatabaseName())) {
                throw new RuntimeException('Source and destination must be different databases.');
            }

            // Open an existing file read-only and take one consistent read snapshot.
            $uri = str_replace(['%', '?', '#'], ['%25', '%3F', '%23'], str_replace('\\', '/', $path));
            $source = new PDO('sqlite:file:'.$uri.'?mode=ro', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $source->exec('PRAGMA query_only=ON');
            $source->beginTransaction();
            $rows = [];
            foreach (self::TABLES as $windows => $web) {
                $rows[$web] = $source->query('SELECT * FROM "'.$windows.'" ORDER BY Id')->fetchAll(PDO::FETCH_ASSOC);
            }
            $source->commit();
            $source = null;

            // Run the entire import and its constraints before creating a backup or saving.
            $this->import($rows, true);
            if (! $this->option('dry-run')) {
                $directory = storage_path('app/private/backups');
                if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
                    throw new RuntimeException('Could not create backup directory.');
                }
                $backup = $directory.'/before-windows-import-'.date('Ymd-His').'-'.Str::random(8).'.sqlite';
                DB::connection()->getPdo()->exec('VACUUM INTO '.DB::connection()->getPdo()->quote($backup));
                $this->info('Web database backup: '.$backup);
                $this->import($rows, false);
            }
            $this->table(['Records', 'Count', 'Amount total'], collect($rows)->map(
                fn (array $records, string $table) => [
                    $table,
                    count($records),
                    in_array($table, ['payments', 'bank_transactions', 'disbursements'])
                        ? number_format(array_sum(array_column($records, 'Amount')), 2, '.', '')
                        : '-',
                ]
            )->values()->all());
            $this->info($this->option('dry-run') ? 'Validation passed. No records saved.' : 'Import complete. Windows data unchanged; web login unchanged.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }

    private function import(array $rows, bool $dryRun): void
    {
        DB::beginTransaction();
        try {
            foreach (['members', 'payments', 'bank_transactions', 'disbursements'] as $table) {
                if (DB::table($table)->exists()) {
                    throw new RuntimeException('Import stopped: web business records already exist. This command only imports into an empty web database.');
                }
            }
            $names = array_column($rows['collection_cycles'], 'Name');
            if (DB::table('collection_cycles')->whereNotIn('name', $names)->exists()) {
                throw new RuntimeException('Web collection cycles not found in the Windows database exist. Review them before importing.');
            }

            $ids = [];
            foreach (self::TABLES as $table) {
                $columns = Schema::getColumnListing($table);
                foreach ($rows[$table] as $row) {
                    $record = [];
                    foreach ($row as $key => $value) {
                        if ($key === 'Id') {
                            continue;
                        }
                        $column = $key === 'CycleId' ? 'collection_cycle_id' : Str::snake($key);
                        if (! in_array($column, $columns, true)) {
                            throw new RuntimeException("Unsupported Windows field: {$table}.{$key}. No data was imported.");
                        }
                        $record[$column] = $value;
                    }

                    if ($table === 'members' && isset($record['start_cycle_id'])) {
                        $record['start_cycle_id'] = $ids['collection_cycles'][$record['start_cycle_id']]
                            ?? throw new RuntimeException('Member refers to a missing start cycle.');
                    }
                    if ($table === 'payments') {
                        $record['member_id'] = $ids['members'][$record['member_id']]
                            ?? throw new RuntimeException('Payment refers to a missing member.');
                        $record['collection_cycle_id'] = $ids['collection_cycles'][$record['collection_cycle_id']]
                            ?? throw new RuntimeException('Payment refers to a missing collection cycle.');
                    }
                    $record['created_at'] ??= now()->toDateTimeString();
                    $record['updated_at'] = now()->toDateTimeString();

                    $existing = $table === 'collection_cycles'
                        ? DB::table($table)->where('name', $record['name'])->value('id')
                        : null;
                    if ($existing !== null) {
                        DB::table($table)->where('id', $existing)->update($record);
                        $id = $existing;
                    } else {
                        $id = DB::table($table)->insertGetId($record);
                    }
                    $ids[$table][$row['Id']] = $id;
                }
                if (DB::table($table)->count() !== count($rows[$table])) {
                    throw new RuntimeException('Record count verification failed: '.$table);
                }
                if (in_array($table, ['payments', 'bank_transactions', 'disbursements'])) {
                    $expected = round(array_sum(array_column($rows[$table], 'Amount')), 2);
                    if (abs(round((float) DB::table($table)->sum('amount'), 2) - $expected) > 0.001) {
                        throw new RuntimeException('Amount verification failed: '.$table);
                    }
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }
    }
}
