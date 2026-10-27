# Travo News Portal — setup (CodeIgniter 4.7 + Shield 1.x, PHP 8.2+)

## 1. Copy files
Unzip over your CI4 project root. You get:
- `Travo/Admin/...` and `Travo/FrontEnd/...` (the two modules)
- `app/Database/Migrations/2026-10-13-000000_CreateTravoNewsTables.php`
- `public/uploads/` (post and gallery images go here)

## 2. Install packages
```
composer require codeigniter4/shield codeigniter4/settings
```

## 3. Register the modules (3 small edits in stock files)

**composer.json** – inside `autoload.psr-4` add, then run `composer dump-autoload`:
```
"Travo\\Admin\\": "Travo/Admin/",
"Travo\\FrontEnd\\": "Travo/FrontEnd/"
```

**app/Config/Autoload.php**
```php
public $psr4 = [
    APP_NAMESPACE => APPPATH,
    'Travo\Admin'    => ROOTPATH . 'Travo/Admin',
    'Travo\FrontEnd' => ROOTPATH . 'Travo/FrontEnd',
];
public $helpers = ['url', 'form', 'base', 'home'];   // base_helper + home_helper
```
(In CI 4.5+ `$psr4` is `public $psr4` / `public array $psr4` — keep the existing APP_NAMESPACE line.)

**app/Config/Routing.php**
```php
public string $defaultNamespace = 'Travo\FrontEnd\Controllers';
public bool $autoRoute = false;
```

**app/Config/Routes.php** – delete the default `$routes->get('/', 'Home::index');` line (module routes are auto-discovered).

**app/Config/Filters.php** – enable CSRF globally: in `$globals['before']` use `'csrf'`.
**app/Config/Security.php** – `public bool $regenerate = false;` (so AJAX comments can post more than once).

## 4. .env
```
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8081/'
database.default.hostname = localhost
database.default.database = newsportal
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
```
Create an empty database `newsportal` (utf8mb4).

## 5. Shield + migrations + first admin
```
php spark shield:setup           # answer yes; it also adds Shield routes/filters
php spark migrate --all          # Shield, Settings and Travo tables
php spark db:seed TravoStarterSeeder  # editable demo stories and starter pages
php spark shield:user create     # username / email / password
php spark shield:user addgroup -n YOURUSERNAME -g superadmin
php spark serve
```
Site: http://localhost:8081  ·  Login: /login  ·  Registration: /register  ·  Admin: /admin
Login accepts either username or email. Public registrations are ordinary `user` accounts; only accounts explicitly added to `admin` or `superadmin` can open the admin workspace. Never assign an admin group through public registration.

## 6. First steps
Admin → Media library (upload images) → Categories → Posts → New post (set Published) → Pages. The seeder's articles and policy pages are labeled starter copy; replace and review them before launch. The media library copies local paths that can be pasted into a post's thumbnail field.

## Security notes
- Every view value uses `esc()`. The only raw echoes are post/page body HTML, which passes through `ContentCleaner::clean()` (tag/attribute whitelist, no scripts, no `javascript:` URLs) on every save.
- Comments are `pending` until approved, honeypot + 3/min throttle per IP.
- Admin routes need group `admin` or `superadmin`; all admin writes are CSRF-protected and logged in `activity_logs`.
- Gallery uploads are limited to verified JPG, PNG, GIF, and WebP images (5 MB each); deletion can break articles that reference an image.
- Configure SMTP in `app/Config/Email.php` or `.env` before relying on email delivery. Do not commit SMTP credentials.
- Before going live: set `CI_ENVIRONMENT = production`, configure a real `app.baseURL`, and never commit `.env`. Bootstrap 5.3.3 is currently loaded from jsDelivr; self-host or pin the assets if your deployment requires local assets or strict availability guarantees.

## Not included yet (blueprint items to add next)
Tags, Settings screens (site name etc. — currently `setting('App.siteName')`, falls back to "Samachar"), roles matrix UI, static page cache, Makalu editor, SEO sitemap.
