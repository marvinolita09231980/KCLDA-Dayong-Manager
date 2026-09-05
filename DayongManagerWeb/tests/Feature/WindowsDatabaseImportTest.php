<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class WindowsDatabaseImportTest extends TestCase
{
    use DatabaseMigrations;

    private string $source;
    private array $existingBackups;

    protected function setUp(): void
    {
        parent::setUp();
        $this->existingBackups = glob(storage_path('app/private/backups/before-windows-import-*.sqlite')) ?: [];
        $this->source = tempnam(sys_get_temp_dir(), 'dayong-test-');
        $pdo = new PDO('sqlite:'.$this->source);
        $pdo->exec(<<<'SQL'
            CREATE TABLE CollectionCycles (Id INTEGER PRIMARY KEY, Name TEXT, Type TEXT, ExpectedAmount REAL, Active INTEGER);
            INSERT INTO CollectionCycles VALUES (42, 'Test cycle', 'Dayong', 100, 1);
            CREATE TABLE Members (Id INTEGER PRIMARY KEY, FirstName TEXT, LastName TEXT, Council TEXT, Active INTEGER, MemberStatus TEXT, StartCycleId INTEGER, ClaimedBenefits TEXT);
            INSERT INTO Members VALUES (75, 'Test', 'Member', 'Council', 0, 'Inactive', 42, 'Test claim');
            CREATE TABLE Payments (Id INTEGER PRIMARY KEY, MemberId INTEGER, CycleId INTEGER, Amount REAL, DatePaid TEXT, ReceiptNumber TEXT, Notes TEXT);
            INSERT INTO Payments VALUES (90, 75, 42, 123.45, '2026-09-01', 'R-1', 'Test note');
            CREATE TABLE BankTransactions (Id INTEGER PRIMARY KEY, TransactionDate TEXT, TransactionType TEXT, Amount REAL, CreatedAt TEXT);
            INSERT INTO BankTransactions VALUES (5, '2026-09-01', 'Deposit', 100.25, '2026-09-01 12:00:00');
            CREATE TABLE Disbursements (Id INTEGER PRIMARY KEY, DisbursementDate TEXT, Amount REAL);
            INSERT INTO Disbursements VALUES (6, '2026-09-02', 23.20);
            SQL);
        DB::table('collection_cycles')->insert(['name' => 'Test cycle', 'type' => 'Dayong', 'expected_amount' => 5]);
    }

    protected function tearDown(): void
    {
        if (isset($this->source)) {
            unlink($this->source);
        }
        foreach (array_diff(glob(storage_path('app/private/backups/before-windows-import-*.sqlite')) ?: [], $this->existingBackups ?? []) as $backup) {
            unlink($backup);
        }
        parent::tearDown();
    }

    public function test_import_preserves_data_and_links_and_rejects_repeat(): void
    {
        $original = hash_file('sha256', $this->source);
        $user = \App\Models\User::factory()->create();
        $this->artisan('dayong:import-windows', ['source' => $this->source])->assertExitCode(0);
        $member = DB::table('members')->first();
        $cycle = DB::table('collection_cycles')->first();
        $this->assertSame($cycle->id, $member->start_cycle_id);
        $this->assertDatabaseHas('members', ['active' => 0, 'claimed_benefits' => 'Test claim']);
        $this->assertDatabaseHas('payments', ['member_id' => $member->id, 'collection_cycle_id' => $cycle->id, 'amount' => 123.45, 'receipt_number' => 'R-1']);
        $this->assertDatabaseHas('bank_transactions', ['amount' => 100.25, 'created_at' => '2026-09-01 12:00:00']);
        $this->assertDatabaseHas('disbursements', ['amount' => 23.20]);
        $this->assertSame($original, hash_file('sha256', $this->source));
        $this->assertSame($user->password, $user->fresh()->password);
        $this->assertDatabaseCount('users', 1);

        $backups = array_diff(glob(storage_path('app/private/backups/before-windows-import-*.sqlite')), $this->existingBackups);
        $this->assertCount(1, $backups);
        $backup = new PDO('sqlite:'.reset($backups));
        $this->assertEquals(0, $backup->query('SELECT COUNT(*) FROM members')->fetchColumn());
        $backup = null;
        $this->artisan('dayong:import-windows', ['source' => $this->source])->assertExitCode(1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_preview_saves_nothing(): void
    {
        $this->artisan('dayong:import-windows', ['source' => $this->source, '--dry-run' => true])->assertExitCode(0);
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseHas('collection_cycles', ['expected_amount' => 5]);
    }

    public function test_bad_relationship_rolls_back_everything(): void
    {
        $pdo = new PDO('sqlite:'.$this->source);
        $pdo->exec('UPDATE Payments SET MemberId = 999');
        $pdo = null;
        $this->artisan('dayong:import-windows', ['source' => $this->source])->assertExitCode(1);
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('collection_cycles', ['expected_amount' => 5]);
    }
}
