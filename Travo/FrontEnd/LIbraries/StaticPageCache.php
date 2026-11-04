<?php

namespace Travo\FrontEnd\LIbraries;

use Config\Services;

/**
 * StaticPageCache — serves rendered front pages as plain HTML files.
 *
 * Clean URLs (no query string) are written to public/static-cache/ as real
 * .html files so Apache can serve them directly (see public/.htaccess),
 * completely bypassing PHP. Paginated pages (?page=N) are stored under
 * _query/ and served through PHP.
 *
 * Pages live until invalidated (post/category change, admin clear) — there is
 * no TTL. Search/admin/auth/dashboard are never cached.
 */
class StaticPageCache
{
    /** Web-accessible cache root. */
    public const ROOT = FCPATH . 'static-cache';

    /** Path prefixes that are never cached (dynamic / private). */
    private const EXCLUDED_PREFIXES = [
        'admin', 'auth', 'login', 'register', 'logout', 'dashboard',
        'search', 'api', 'uploader', 'view', 'blog',
    ];

    /** Page query parameter used for pagination (cached per page number). */
    private const PAGINATION_PARAM = 'page';

    public function isEnabled(): bool
    {
        return setting('StaticCache.enabled') !== '0';
    }

    /**
     * Serve the cached HTML for the current request if it exists.
     * Sends the response and exits on a hit.
     */
    public function serve(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $file = $this->buildFile();
        if ($file === null || !is_file($file)) {
            return;
        }

        $type = str_ends_with($file, '.xml') ? 'application/xml' : 'text/html';

        Services::response()
            ->setContentType($type . '; charset=UTF-8')
            ->setBody((string) file_get_contents($file))
            ->send();

        exit;
    }

    /**
     * Persist rendered HTML for the current request (atomic write).
     */
    public function store(string $content): void
    {
        if (!$this->isEnabled() || $content === '') {
            return;
        }

        $file = $this->buildFile();
        if ($file === null) {
            return;
        }

        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $tmp = $file . '.tmp.' . uniqid();
        if (file_put_contents($tmp, $content, LOCK_EX) !== false) {
            @chmod($tmp, 0664);
            rename($tmp, $file);
        }
    }

    /**
     * Delete the cached file for a logical path (e.g. "news/my-slug",
     * "category/nepal", "index") plus all paginated (_query) variants.
     */
    public function deletePath(string $path): void
    {
        $path = trim($path, '/');
        if ($path === '') {
            $path = 'index';
        }

        if (str_ends_with($path, '.html') || str_ends_with($path, '.xml')) {
            @unlink(self::ROOT . '/' . $path);
        } else {
            @unlink(self::ROOT . '/' . $path . '.html');
        }

        $this->clearQueryCache();
    }

    /**
     * Delete every paginated (_query) cache file.
     */
    public function clearQueryCache(): void
    {
        $this->recursiveDelete(self::ROOT . '/_query');
    }

    /**
     * Remove the entire static cache (all pages + pagination). Keeps the root.
     */
    public function purge(): void
    {
        if (!is_dir(self::ROOT)) {
            mkdir(self::ROOT, 0775, true);
            return;
        }
        $items = scandir(self::ROOT);
        foreach ($items ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->recursiveDelete(self::ROOT . DIRECTORY_SEPARATOR . $item);
        }
    }

    /**
     * Recursively delete a directory (and everything in it), or a single file.
     */
    private function recursiveDelete(string $target): void
    {
        if (is_file($target)) {
            @unlink($target);
            return;
        }
        if (!is_dir($target)) {
            return;
        }
        $items = scandir($target);
        foreach ($items ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->recursiveDelete($target . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($target);
    }

    /**
     * Resolve the current request to a cache file path, or null when the page
     * should not be cached.
     */
    private function buildFile(): ?string
    {
        $uri = Services::request()->getUri();
        $path = trim((string) $uri->getPath(), '/');

        if ($path === '') {
            $path = 'index';
        }

        $first = explode('/', $path)[0] ?? '';
        if (\in_array(strtolower($first), self::EXCLUDED_PREFIXES, true)) {
            return null;
        }

        // Sanitize every segment — only safe URL characters (prevents traversal).
        $segments = explode('/', $path);
        foreach ($segments as $seg) {
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $seg)) {
                return null;
            }
        }
        $path = implode('/', $segments);

        // Paginated pages (clean of other query noise) → _query/<hash>.html
        $page = Services::request()->getGet(self::PAGINATION_PARAM);
        if ($page !== null && $page !== '' && ctype_digit((string) $page)) {
            $key = self::PAGINATION_PARAM . '=' . (int) $page;
            return self::ROOT . '/_query/' . sha1($path . '?' . $key) . '.html';
        }

        if (str_ends_with($path, '.html') || str_ends_with($path, '.xml')) {
            return self::ROOT . '/' . $path;
        }

        return self::ROOT . '/' . $path . '.html';
    }
}
