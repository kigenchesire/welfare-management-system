# Welfare Group Management System (WMS)

A web application for running a bereavement welfare group: member registration and approval, beneficiaries, welfare cases, contribution invoices, member wallets, deposits, custom invoices, reports, SMS and audit trails.

It is built to be **reused by any welfare group**. The group name, logo, colours, website content, contribution amounts, payment details and SMS gateway are all set from the admin panel. Nothing group-specific is in the code. The first installation is seeded for **Luxurious Welfare Group**.

- Laravel 10, PHP 8.1+, MySQL / MariaDB
- Blade + Bootstrap 5 + jQuery + DataTables + Chart.js, loaded from a CDN
- **No Node.js, npm, Vite or build step.** Only Composer is needed.

---

## 1. Requirements

| Item | Version |
|---|---|
| PHP | 8.1 or newer (8.2+ recommended) |
| PHP extensions | bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, zip |
| MySQL / MariaDB | MySQL 5.7+ or MariaDB 10.3+ |
| Composer | 2.x |

## 2. Installation (local or VPS)

```bash
# 1. Get the code into a folder, then install PHP dependencies
composer install --no-dev --optimize-autoloader

# 2. Create the environment file and app key
cp .env.example .env
php artisan key:generate

# 3. Create an empty MySQL database (utf8mb4), then edit .env:
#    DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL

# 4. Create tables and the initial data (roles, permissions, admin, default settings)
php artisan migrate --seed

# 5. Make uploaded images (logo, photographs) publicly reachable
php artisan storage:link

# 6. Run it
php artisan serve        # http://127.0.0.1:8000
```

Optional sample data for trying the system (**not for production**):

```bash
php artisan db:seed --class=DemoSeeder
```

This creates `chairperson@example.com`, `secretary@example.com` and `treasurer@example.com` (password `123456`), plus 5 active members and 1 pending registration.

## 3. Default login

| Username | Password | Role |
|---|---|---|
| `admin@example.com` | `123456` | Administrator |

> **Change the administrator password before going live** (top-right menu > Change password). Also create real staff accounts under Administration > Users.

All new accounts (staff and approved members) start with the password **`123456`**. Passwords can be at most **6 characters**, with no other complexity rules. Members sign in with their **National ID** (or phone / email).

## 4. Shared hosting (cPanel) deployment

1. On your computer, run `composer install --no-dev --optimize-autoloader` and upload the whole project, `vendor/` included, to a folder **outside** `public_html`, e.g. `/home/user/wms`.
2. Point the domain's document root to `/home/user/wms/public`. If you cannot change it, copy the contents of `public/` into `public_html` and edit `public_html/index.php` so the two `__DIR__.'/../'` paths point to `/home/user/wms/`.
3. Create the MySQL database and user in cPanel, then fill in `.env` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`).
4. Run once over SSH or the cPanel Terminal: `php artisan key:generate`, `php artisan migrate --seed --force`, `php artisan storage:link`. If you have no terminal, create the `public/storage` symlink with the host's file manager or ask support.
5. Make `storage/` and `bootstrap/cache/` writable (755 or 775).
6. For speed, optionally run: `php artisan config:cache && php artisan route:cache && php artisan view:cache`. Run `php artisan config:clear` after any later `.env` change.

## 5. Configuring for a welfare group (no code changes)

Sign in as Administrator and open:

- **System Settings > General**: name, short name, logo, favicon, description, address, phone, email, website, primary/secondary colours, registration information.
- **System Settings > Financial**: contribution amount per case type (parent, parent-in-law, self, spouse, child), currency, payment method name, Paybill number, account-number instructions, payment steps, member number prefix, low-balance alert level, and two options:
  - *Use new deposits to settle outstanding invoices automatically* (on by default)
- **System Settings > SMS Gateway**: enable SMS, provider, API URL, username, API key, Sender ID, message templates, and a test-SMS button.
- **Website Management**: hero text and photo, about us, mission, vision, objectives, values, "how it works" texts, representative name, photo, title and bio, social links, footer text, plus a gallery of extra photographs.

Contribution amounts apply to **new** cases. Existing cases keep the amount they were raised with.

### Public website (currently switched off)

The public homepage and online registration are **disabled for now**: opening the site goes straight to the login page, and members are registered by the Secretary (Members > Add member). The code is kept. To switch it back on, follow the comment at the top of `routes/web.php` (restore the two `HomeController` routes, remove the redirect) and uncomment the "website" links marked `Public website disabled for now` in the login and layout views.

### Installing as an app (phone and computer)

The system is a Progressive Web App (PWA), so it can be installed like an app with its own icon and window. Nothing is published to an app store.

- **Android (Chrome, Samsung Internet, Edge):** tap **Install app** on the login page or top bar, or use the browser menu > *Install app* / *Add to Home screen*.
- **Windows, Mac, Linux, ChromeOS (Chrome or Edge):** click **Install app**, or the install icon at the right of the address bar. The app then opens in its own window and appears in the Start menu or Applications.
- **iPhone / iPad (Safari):** tap **Install app** for instructions, or Share > *Add to Home Screen*.

The app name, icon and colour come from System Settings: the uploaded **logo** is used for the icon, or the short name's initials on the primary colour if there is no logo. Changing them updates the icon for new installs, and for existing installs after the browser refreshes the manifest.

**Requirements:** installing only works over **HTTPS** (or on `localhost` when testing). On cPanel, enable the free AutoSSL / Let's Encrypt certificate and set `APP_URL=https://...`.

**Privacy:** pages with member or financial data are never stored on the device. Without a connection the app shows an "offline" notice. Only styles, scripts and icons are cached. After changing `public/css/app.css` or `public/js/app.js`, raise `VERSION` in `public/sw.js` so installed apps fetch the new files.

## 6. How the money works

- Every member has a **wallet**. Its balance changes **only** through `App\Services\WalletService`. Each change runs in a database transaction, locks the wallet row and writes one immutable `wallet_transactions` row with the balance before and after. Ledger rows cannot be edited or deleted.
- **Deposits** (Treasurer > Record deposit) credit the wallet. Each Paybill transaction code can be recorded only once. A receipt (print/PDF) is produced. The **transaction cost** field is optional and is recorded for reference only: it is shown on the receipt and the deposit report but is **not** added to the wallet.
- **Welfare case** (Secretary > New welfare case): select the member and the deceased beneficiary. The category, and so the amount, comes from settings. One invoice is raised per active member, protected by a unique `(welfare_case_id, member_id)` index. Each wallet is charged automatically:
  - enough balance: invoice paid;
  - some balance: the available amount is taken and the rest stays **outstanding**;
  - zero balance: the full amount is outstanding.
  Wallets never go negative.
- **Who is invoiced:** every active member, **including the member who reported the bereavement**. Only when the member themself has died (a "self" case) are they not invoiced.
- **Paying for a specific case:** on the case page, **Record payment** (or **Pay** on a member's row) records a Paybill deposit against that case. The money is credited to the wallet and used first for that member's invoice on this case, fully or partly; anything extra stays in the wallet (or settles other invoices if auto-settle is on).
- **Reminders:** on the case page, **Remind unpaid** sends an SMS to every member who has not fully paid that case, each with their own outstanding amount; **Remind** on a row reminds one member. The text is set in System Settings > SMS Gateway (*case contribution reminder*). Members also get a dashboard notification.
- **Invoice new members** on a case only invoices members who don't already have an invoice for it, so running it again is safe.
- **Cancelling** a case or invoice refunds whatever was deducted, through refund entries in the ledger.
- **Mistakes** are fixed with **payment reversals** or **adjustments**, never by deleting records. A debit that would make a balance negative needs an explicit "authorise negative balance" tick.
- **Financial overview** reports wallet balances as a **liability** (money held for members), kept separate from contributions actually collected.
- Forms that change data carry a one-time token, so double clicks or refreshes cannot post the same thing twice.

## 7. Roles

| Role | Can do |
|---|---|
| Administrator | Everything, including settings, users, roles and permissions, and audit logs |
| Chairperson | View members, cases, invoices, wallets, financial overview, all reports, audit logs |
| Secretary | Register/approve/reject/edit/suspend members, manage beneficiaries (the only role besides Admin), create/close/cancel cases, announcements, website content |
| Treasurer | Record deposits, reverse payments, wallets, adjustments, custom invoices, financial reports |
| Member | Own profile, beneficiaries (read only), wallet statement, invoices, payments and receipts, welfare cases |

Permissions are enforced on the server for every route. Role permissions can be changed under Administration > Roles & Permissions. Members' pages only ever load the signed-in member's own records.

## 8. SMS

`App\Services\SmsService` is the only class that talks to an SMS gateway. It supports **Africa's Talking** and a **generic JSON HTTP** gateway. To add another provider, add a branch in `SmsService::dispatch()`. Every message is logged in **SMS Logs** with its status. Gateway failures never undo a payment or a case. Bulk messages (new case, announcements) are sent after the page has responded.

Credentials can be set in System Settings or in `.env` (`SMS_ENABLED`, `SMS_API_URL`, `SMS_API_KEY`, `SMS_USERNAME`, `SMS_SENDER_ID`). Settings saved in the admin panel take priority.

## 9. Reports

Reports > choose a report. Every report has date filters, search, on-screen DataTables, **Print**, **PDF** and **Excel** export:
member register, active/inactive members, registrations, beneficiary register, welfare cases, contributions per case, member payment status, outstanding contributions, wallet balances, wallet transactions, deposits, custom invoices, financial summary.

## 10. Backups

Back up the database and the `storage/app/public` folder (uploaded images) regularly, for example a daily cPanel backup or:

```bash
mysqldump -u USER -p DB_NAME > wms-$(date +%F).sql
```

## 11. Tests

Tests need a separate empty MySQL database named `welfare_system_test` (see `phpunit.xml`):

```bash
php artisan test
```

They cover the registration and approval workflow, the password rule, beneficiary limits and permissions, case invoicing with full, partial and zero balances, duplicate prevention, deposits, reversals, cancellations and refunds, ledger consistency, role authorisation, member data isolation, and every page and report export.

## 12. Security notes

- `APP_DEBUG=false` in production. The debug page must never be public.
- Laravel 10 reached end of security support in February 2025. This project uses Laravel 10 as specified; `composer audit` lists advisories that are only fixed in Laravel 12/13. The project avoids the affected features: no signed URLs, email validation uses `email:filter`, and debug is off in production. Plan an upgrade to a supported Laravel version.
- Uploaded images are restricted to JPG/PNG/WEBP up to 3 MB (favicon 512 KB).
- The login form is rate-limited (5 failed attempts per minute per username + IP).
