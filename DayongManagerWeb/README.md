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

To start the web app automatically when you sign in to Windows, run
`powershell -ExecutionPolicy Bypass -File .\install-autostart.ps1` once from this
directory. It creates a shortcut in your Windows Startup folder and starts
the server without opening a browser. Use `start.ps1` whenever you want to
open the app immediately. To stop automatic startup, delete the
`Dayong Manager Web.lnk` shortcut from the Startup folder (`Win+R`, then
`shell:startup`).

Change this temporary password immediately after first login. For deployment, use PHP 8.2+ with `mbstring`, `fileinfo`, `intl`, PDO and the driver for your database. Run `composer install`, `php artisan migrate --seed`, and point the web server document root at `public/`.

## Free online access from this computer

To start online access automatically at Windows sign-in, run
`powershell -ExecutionPolicy Bypass -File .\install-online-autostart.ps1`
after completing online setup. It installs **KCLDA Online.lnk** in the current
user's Windows Startup folder. It runs hidden, retries a stopped launcher every
60 seconds, and writes diagnostics to `storage/app/private/online/autostart.log`.
You can close VS Code and visible PowerShell windows after this automatic
launcher starts. The laptop must remain awake and connected to the internet.
Startup happens after signing in, not before the Windows sign-in screen.
To disable future automatic starts, press Win+R, enter `shell:startup`, and
delete **KCLDA Online.lnk**. Restart Windows to stop its running background processes.

The online launcher uses a free ngrok account and a separate local server on
port 8001. Your computer and internet connection must stay on, with the launcher
running. Your database remains on this computer.

1. Create a free account at <https://dashboard.ngrok.com/signup> and copy the
   assigned domain from <https://dashboard.ngrok.com/domains>.
2. Change the starter password for every active account in the local app.
3. Run this command in PowerShell in the project directory:

   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File .\start-online.ps1 -Setup
   ```

4. Enter your assigned domain and authtoken at the local prompts. The token prompt
   is hidden; do not send the token in chat. Find it at
   <https://dashboard.ngrok.com/get-started/your-authtoken>.
5. Open the printed HTTPS address and bookmark it as **KCLDA Dayong Manager**.
   The launcher also creates a **KCLDA Online.url** shortcut in this directory.

If ngrok reports ERR_NGROK_313, the saved domain is not assigned to your account.
Run `powershell -ExecutionPolicy Bypass -File .\start-online.ps1 -ChangeDomain`
and paste the exact domain from your ngrok Domains page. This keeps your saved token.

For later launches, run the same command without `-Setup`. Keep the window open;
press Ctrl+C to stop online access. Online mode uses production settings,
disables debugging, and enables HTTPS-only session cookies without editing `.env`.
It checks the starter passwords, configuration cache, and frontend build before
opening the tunnel. Follow any readiness message and retry.

The launcher downloads the official Windows x64 ngrok client and checks its
publisher signature. If downloading fails, get the Windows x64 ZIP from
<https://ngrok.com/download/windows> and put `ngrok.exe` in
`storage/app/private/online/`. Account settings, the token, client, and logs
are stored in that private folder, which is excluded from Git. Keep it private.

The free plan assigns a permanent domain; it does not let you choose a name such
as `kclda.ngrok-free.app`. It has monthly traffic limits and a browser introduction
screen: <https://ngrok.com/docs/pricing-limits/free-plan-limits>.
This setup uses PHP's development server for small-scale remote access;
continuous production hosting requires a production web server.

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

## Outstanding payables

Open **Finance → Outstanding Payables** to record an unpaid commitment with its
payee, category, description, amount, and optional due date. Outstanding items
reduce the available Dayong balance without changing cash or creating a ledger
disbursement. They remain outstanding across financial period closures.

When the full amount is actually paid, use **Add to Disbursements** and enter the payment date and
voucher number. This creates one linked disbursement automatically; do not record
another disbursement for the same payment. Use the **Paid** filter to see settled
items. Paid items and their linked disbursements cannot be edited or deleted.
Unpaid entries can be corrected or removed. Partial payments are not supported.

The dashboard, Financial Ledger, Financial Report, and Collection Statistics
(including its PDF) show cash, outstanding payables, and available funds. The
current balance summary is independent of date filters. Bank ledger transactions
continue to track deposits and withdrawals separately.

Access follows disbursement permissions: view, create, edit, and delete. Paying
requires both create and edit permissions. Install the new table with
`php artisan migrate` when updating another installation.

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

