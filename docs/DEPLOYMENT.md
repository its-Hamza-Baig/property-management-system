# Deploying the Property Management System

Written for ordinary shared or VPS hosting with cPanel or SSH. No cloud
infrastructure, containers or CI pipeline is required.

---

## 1. What the hosting must provide

| Requirement | Minimum |
|---|---|
| PHP | 8.2 or newer, with `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `curl`, `xml`, `ctype`, `json` |
| MySQL | 8.0, or MariaDB 10.6+ |
| HTTPS | A valid SSL certificate. Let's Encrypt is fine. |
| Cron | One entry, running every minute |
| Disk | The application plus room for uploads and backups |

`zip` and `bcmath` are **not** required. Money arithmetic uses integer paisa
and does not depend on bcmath, and the backup falls back to a pure PHP dump
when `mysqldump` or `exec()` is unavailable.

---

## 2. Point the domain at `public/`

This is the single most important step. The web root — the "document root" in
cPanel — must be the **`public`** folder, not the folder above it.

```
/home/youruser/pms/            <- the application (not reachable from the web)
/home/youruser/pms/public/     <- the document root
```

If you get this wrong, `.env`, the database backups and the uploaded documents
all become downloadable over the internet.

On shared hosting where the document root is fixed at `public_html`, put the
application outside it and either symlink or edit `public/index.php` paths:

```bash
ln -s /home/youruser/pms/public /home/youruser/public_html
```

---

## 3. Upload and install

```bash
cd /home/youruser/pms
# upload the project here, or: git clone <your repo> .

composer install --no-dev --optimize-autoloader
```

`--no-dev` leaves out the test tooling, which production does not need.

---

## 4. Configure

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```ini
APP_NAME="Property Management System"
APP_ENV=production
APP_DEBUG=false                 # never true on a live site
APP_URL=https://your-domain.com
APP_TIMEZONE=Asia/Karachi

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true      # cookies only over HTTPS
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="no-reply@your-domain.com"

PMS_SUPER_ADMIN_EMAIL=you@your-domain.com
PMS_BACKUP_RETENTION_DAYS=14
```

`APP_DEBUG=false` matters: with it on, an error page shows your configuration
and database details to whoever triggered the error.

---

## 5. Create the tables

```bash
php artisan migrate --force
php artisan db:seed --force
```

`--force` is what lets a migration run in production without an interactive
confirmation.

The seeder prints the administrator login. **Sign in and change that password
immediately**, or create your own and remove the default:

```bash
php artisan pms:create-super-admin
```

---

## 6. Permissions

The web server user needs to write to exactly two directories:

```bash
chmod -R 775 storage bootstrap/cache
chown -R youruser:www-data storage bootstrap/cache   # adjust to your host
```

Nothing else in the project should be writable by the web server.

---

## 7. Production caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Re-run all three after **every** deployment and after any `.env` change,
otherwise the old values stay cached.

---

## 8. The cron entry

One line, running every minute. Laravel decides what is actually due.

```
* * * * * cd /home/youruser/pms && php artisan schedule:run >> /dev/null 2>&1
```

In cPanel: **Cron Jobs → Add New Cron Job → Once Per Minute**.

Check it is working:

```bash
php artisan schedule:list
```

This drives the nightly backup and the installment reminders. Without it there
are no backups.

---

## 9. Check the private folders are not public

Open these in a browser. Every one must give **403 or 404**, never a file:

```
https://your-domain.com/.env
https://your-domain.com/storage/logs/laravel.log
https://your-domain.com/../.env
```

Uploaded documents live in `storage/app/private-documents` and backups in
`storage/app/backups`. Both are outside `public/` and are never symlinked into
it. Documents are only ever served through `/documents/{id}/download`, which
checks the policy first.

Do **not** run `php artisan storage:link` unless you specifically need the
public disk; it is not needed by this application.

---

## 10. Backups, and proving they work

The nightly job writes a `.sql` file into `storage/app/backups` and deletes
anything older than `PMS_BACKUP_RETENTION_DAYS`. Every attempt, successful or
not, is recorded and shown under **Platform → Backups**, and administrators are
notified when one fails.

Run one by hand now:

```bash
php artisan pms:backup
ls -lh storage/app/backups/
```

### Test the restore before you rely on it

A backup you have never restored is not a backup. Do this once at setup, and
again after any hosting change:

```bash
# 1. Make a scratch database
mysql -u root -p -e "CREATE DATABASE pms_restore_test CHARACTER SET utf8mb4;"

# 2. Load the newest backup into it
mysql -u root -p pms_restore_test < storage/app/backups/pms-YYYY-MM-DD-HHMMSS.sql

# 3. Check it actually arrived
mysql -u root -p -e "
  SELECT COUNT(*) AS tables_restored
  FROM information_schema.tables
  WHERE table_schema='pms_restore_test' AND table_type='BASE TABLE';
  SELECT COUNT(*) AS journals FROM pms_restore_test.journals;
  SELECT COUNT(*) AS ledger_entries FROM pms_restore_test.ledger_entries;"

# 4. Prove the restored books still balance: this must return 0.00
mysql -u root -p -e "
  SELECT ROUND(SUM(debit) - SUM(credit), 2) AS must_be_zero
  FROM pms_restore_test.ledger_entries;"

# 5. Clean up
mysql -u root -p -e "DROP DATABASE pms_restore_test;"
```

**Documents are not inside the .sql file.** Take them separately, and keep the
two in step — a database restored to Tuesday with files from Friday will have
document rows pointing at files that do not exist:

```bash
tar -czf documents-$(date +%F).tar.gz storage/app/private-documents
```

Copy both the `.sql` and the `.tar.gz` off the server — another host, or
encrypted object storage. A backup sitting on the same disk as the database
does not survive losing that disk.

---

## 11. Deploying an update

```bash
cd /home/youruser/pms
php artisan down                       # maintenance page

git pull                               # or upload the new files
composer install --no-dev --optimize-autoloader
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
```

Take a backup before any release that includes a migration.

---

## 12. Security checklist for go-live

- [ ] Document root is `public/`
- [ ] HTTPS works and `SESSION_SECURE_COOKIE=true`
- [ ] `APP_DEBUG=false` and `APP_ENV=production`
- [ ] The default `admin@example.com` password has been changed or the account removed
- [ ] `.env` returns 403/404 over the web
- [ ] `storage` and `bootstrap/cache` are writable; nothing else is
- [ ] The cron entry is running (`php artisan schedule:list`)
- [ ] A backup has been taken **and restored** following section 10
- [ ] Off-site copies of the database and the documents exist
- [ ] `php artisan test` passed before release

---

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| Blank white page | Look in `storage/logs/laravel.log`. Usually file permissions or a missing PHP extension. |
| "Please provide a valid cache path" | `bootstrap/cache` is not writable. |
| Changes to `.env` do nothing | Run `php artisan config:cache` again. |
| 419 on every form | Session cookies are not sticking. Check `SESSION_DOMAIN` and that HTTPS is consistent. |
| Emails never arrive | Check the `MAIL_*` settings. In local development mail goes to `storage/logs/laravel.log` instead. |
| No backups appearing | The cron entry is missing or pointing at the wrong folder. |
| Uploads rejected | The plan limits the file type, the file size or the total storage. Check **Settings → Your plan**, and `upload_max_filesize` in PHP. |
