<?php
if (! function_exists('site_name')) {
    function site_name(): string
    {
        return setting('Travo.siteName') ?: setting('App.siteName') ?: 'Samachar';
    }
}
if (! function_exists('site_tagline')) {
    function site_tagline(): string
    {
        return (string) (setting('Travo.siteTagline') ?? '');
    }
}
if (! function_exists('site_posts_per_page')) {
    function site_posts_per_page(): int
    {
        return min(50, max(1, (int) (setting('Travo.postsPerPage') ?: 12)));
    }
}
if (! function_exists('post_img')) {
    function post_img(?string $path): string
    {
        if ($path) {
            if (in_array(parse_url($path, PHP_URL_SCHEME), ['http', 'https'], true)) {
                return $path;
            }

            return base_url($path);
        }
        return 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360"><rect width="100%" height="100%" fill="#e2e8f0"/><text x="50%" y="50%" fill="#94a3b8" font-size="28" text-anchor="middle" font-family="sans-serif">No image</text></svg>');
    }
}
if (! function_exists('time_ago')) {
    function time_ago(?string $date): string
    {
        if (! $date) {
            return '';
        }
        $d = time() - strtotime($date);
        return match (true) {
            $d < 60 => 'just now',
            $d < 3600 => floor($d / 60) . ' min ago',
            $d < 86400 => floor($d / 3600) . ' hr ago',
            $d < 604800 => floor($d / 86400) . ' d ago',
            default => date('M j, Y', strtotime($date)),
        };
    }
}
if (! function_exists('menu_categories')) {
    function menu_categories(): array
    {
        return cache()->remember('menu_cats', 300, fn() => db_connect()->table('blog_categories')
            ->where('status', 1)->where('on_menu', 1)->orderBy('sort_order')->get()->getResultArray());
    }
}
if (! function_exists('footer_pages')) {
    function footer_pages(): array
    {
        return cache()->remember('footer_pages', 300, fn() => db_connect()->table('pages')
            ->select('title, slug')->where('status', 1)->get()->getResultArray());
    }
}
