# Online Bookstore Management System

A PHP 8+ and MySQL bookstore application for local development with XAMPP. The system will use server-rendered HTML, custom CSS, vanilla JavaScript, and PDO; it will not depend on a PHP framework or frontend framework.

## Project status

Phases 1–8 now have implementation foundations: the normalized MySQL schema, PDO connection helper, responsive database-backed storefront and catalogue, protected customer accounts, database-backed cart, and transactional Cash-on-Delivery orders. A first protected admin overview/order desk now supports guarded status changes, COD collection records, and cancellation stock restoration. Runtime checks are pending because PHP/MySQL command-line tools are unavailable in this workspace.

## Planned implementation

1. **Workspace and initialization — implemented** — inspect the environment, establish project conventions, and initialize Git.
2. **Database foundation — implemented, import unverified** — create `bookstore_db`, schema/sample records, and PDO configuration.
3. **UI foundation — implemented, browser unverified** — create responsive shared layouts and database-backed storefront.
4. **Catalogue and search — implemented, runtime unverified** — add listings, details, filters, sorting, and pagination.
5. **Authentication and access control — implemented, runtime unverified** — add session/CSRF protections, customer sign-in and registration, throttling, and role-check helpers.
6. **Customer accounts — implemented, runtime unverified** — add protected profile/password settings, address management, wishlist management, and paginated order history.
7. **Cart and checkout — implemented, runtime unverified** — persist customer carts, calculate totals from current database prices/settings, and validate coupons, addresses, and stock.
8. **Orders and inventory — partially implemented, runtime unverified** — place Cash-on-Delivery orders transactionally, deduct stock, record inventory movements, manage order transitions, and restore eligible unpaid cancellations; catalogue/inventory administration and refunds remain.
9. **Payments — partial, runtime unverified** — Cash on Delivery is recorded as unpaid until an administrator records collection; online provider adapters and reconciliation remain unconfigured.
10. **Administration — partially implemented, runtime unverified** — protected real-query overview and order desk; catalogue, customer, and direct inventory management remain.
11. **Reports** — build database-backed analytics, printable reports, and CSV export.
12. **Notifications and reviews** — add configurable email delivery and moderated, purchase-verified reviews.
13. **Security and verification** — run focused checks and address findings; report only tests actually performed.
14. **Documentation and final verification** — complete academic and operational documentation and verify the assembled system.

## Planned structure

```text
app/
  Controllers/
  Middleware/
  Models/
  Services/
  Views/
config/
database/
docs/
public/
  assets/
storage/
```

## Local setup

1. Install XAMPP with PHP 8+ (`pdo_mysql` enabled) and MySQL 8.0.16+, then place this project under the XAMPP `htdocs` directory.
2. Copy `.env.example` to `.env` and set local database and application values. Do not commit `.env`.
3. Import `database/bookstore.sql` through phpMyAdmin into a new `bookstore_db`. The installer does not drop existing data; it is intended for a fresh database.
4. Create the initial admin using the CLI instructions below. The installer does not seed a shared/default admin password.
5. Open `http://localhost/Harl/public/` through Apache. The project root and sensitive configuration directories are blocked by `.htaccess`; for deployment, configure Apache's document root to `public/`.

### Initial administrator

The one-time CLI script uses PHP `password_hash()` and refuses to create another admin when one already exists. Do not put the password in `.env`, source code, or a command-line argument. In PowerShell, enter it through a hidden prompt:

```powershell
$secure = Read-Host "Initial administrator password" -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try {
  $env:ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
  & "C:\xampp\php\php.exe" ".\database\create_admin.php"
} finally {
  Remove-Item Env:\ADMIN_PASSWORD -ErrorAction SilentlyContinue
  [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
}
```

The script prompts for the administrator's name and email. Use a password with at least 12 characters and no more than 72 bytes. To reset an existing administrator, use the same hidden PowerShell prompt and run `database\reset_admin_password.php` instead; it updates only an existing admin account, increments its session version to invalidate existing authenticated sessions on their next protected request, and does not change its role or status.

### Current local database

This workspace's ignored `.env` is configured to connect to the existing XAMPP database `harl` at `127.0.0.1:3306`. It already contains the required 25 tables and 10 active sample books, so it was not re-imported or modified during configuration. Fresh installations may continue to use `bookstore_db`; set `DB_DATABASE` in `.env` to match the database you intend to use.

## Validation status

JavaScript syntax and static checks for required tables, sample books, catalogue filters, customer/admin authorization and ownership filters, CSRF-protected POST forms, CSS balance, and whitespace passed. Customer checkout stores Cash on Delivery as unpaid, recalculates coupons and totals on the server, and uses a database transaction for orders, order items, stock deductions, inventory logs, payment records, shipments, and cart clearing. Admin cancellation/restock and COD collection changes are also transactional and activity-logged. PHP linting and MySQL import/queries have not been run because their command-line tools are unavailable in this workspace.

## Configuration

`.env.example` contains non-secret development placeholders only. Use environment-specific values in `.env`; do not store live credentials or provider API keys in source control.
