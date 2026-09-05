# KCLDA Dayong Manager Web

A separate Laravel 12 + Filament 5 web version of the KCLDA Dayong Membership and Collection Manager.

## Included

- Secure Filament admin panel and active-user access control
- Member registry, beneficiary and claims details
- Collection cycles and payment recording
- Bank ledger and disbursement ledger
- Dashboard totals for membership, collections, expenses, available funds and bank balance
- Administrator-managed users and granular permissions
- Good Standing & Compliance under Membership, with council/search/recommendation filters and member review links
- Import, Export & Database Backup under Administration: member CSV template/import, business CSV exports, Windows database upload, and complete SQLite backup downloads
- SQLite by default; MySQL/PostgreSQL can be selected in `.env`

## Run locally on this computer

The system-wide `php` command is PHP 7.4 and is not compatible with Filament 5. A working PHP 8.2 installation is already available, so run:

```powershell
cd DayongManagerWeb
.\start.ps1
```

Open <http://127.0.0.1:8000/admin> and sign in with:

- Username: `admin`
- Password: `Dayong@2026`

Change this temporary password immediately after first login. For deployment, use PHP 8.2+ with `mbstring`, `fileinfo`, `intl`, PDO and the driver for your database. Run `composer install`, `php artisan migrate --seed`, and point the web server document root at `public/`.

## Import the Windows database locally

Keep using the existing web username and password. The importer copies business
records, including member details, claims, cycles, payments, bank transactions,
and disbursements. Windows login accounts are separate and are not imported.

Run migrations, then preview the import:

```powershell
php artisan migrate
php artisan dayong:import-windows "C:\Users\Acer TravelMate\AppData\Local\KCLDA\DayongManager\dayong.db" --dry-run
```

To save the records, run the same import command without `--dry-run`.
The command backs up the web database under `storage/app/private/backups`,
reads Windows data without modifying it, checks counts and amount totals, and
imports all records in one transaction. Existing starter cycles are matched by
name, and member/payment relationships are mapped to the web IDs.

This is a one-time import into an empty local SQLite web database. A second run
is refused if members or transactions already exist, preventing duplicates or
overwriting web edits. Later changes in either app do not synchronize.
Backups and local databases are excluded from Git.

## Web data tools

Refresh the admin panel to access **Membership → Good Standing & Compliance** and
**Administration → Import, Export & Database Backup**. Administrators have access;
other users need `compliance.view` or the relevant `tools.import`, `tools.export`,
and `tools.backup` permissions assigned through Roles & Permissions.

CSV import adds new members using the downloadable template. Invalid or duplicate
rows cancel the entire import. CSV exports cover members, cycles, payments, bank
transactions and disbursements, and can be opened in Excel. They are reports, not
database restore files. Windows database upload uses the same one-time importer
described above. PHP upload limits apply.

Database backup downloads a consistent SQLite snapshot and retains a copy in
`storage/app/private/backups`. It includes accounts and permissions; store it
securely. Backup download currently supports SQLite only.

