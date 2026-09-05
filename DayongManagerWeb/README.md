# KCLDA Dayong Manager Web

A separate Laravel 12 + Filament 5 web version of the KCLDA Dayong Membership and Collection Manager.

## Included

- Secure Filament admin panel and active-user access control
- Member registry, beneficiary and claims details
- Collection cycles and payment recording
- Bank ledger and disbursement ledger
- Dashboard totals for membership, collections, expenses, available funds and bank balance
- Administrator-managed users and granular permissions
- SQLite by default; MySQL/PostgreSQL can be selected in `.env`

## Run locally on this computer

The system-wide `php` command is PHP 7.4 and is not compatible with Filament 5. A working PHP 8.2 installation is already available, so run:

```powershell
cd DayongManagerWeb
.\start.ps1
```

Open <http://127.0.0.1:8000/admin> and sign in with:

- Email: `admin@kclda.local`
- Password: `Dayong@2026`

Change this temporary password immediately after first login. For deployment, use PHP 8.2+ with `mbstring`, `fileinfo`, `intl`, PDO and the driver for your database. Run `composer install`, `php artisan migrate --seed`, and point the web server document root at `public/`.
