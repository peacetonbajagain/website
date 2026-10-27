<?php
if (! function_exists('create_unique_slug')) {
    function create_unique_slug(string $table, string $title, ?int $ignoreId = null): string
    {
        $base = url_title($title, '-', true) ?: 'item-' . substr(md5($title . microtime()), 0, 6);
        $slug = $base;
        $i = 2;
        $db = db_connect();
        while (true) {
            $q = $db->table($table)->where('slug', $slug);
            if ($ignoreId) {
                $q->where('id !=', $ignoreId);
            }
            if ($q->countAllResults() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }
}
if (! function_exists('log_activity')) {
    function log_activity(string $action, string $module, $targetId = null, ?string $details = null): void
    {
        $u = auth()->user();
        db_connect()->table('activity_logs')->insert([
            'user_id' => $u?->id, 'user_name' => $u?->username, 'module' => $module, 'action' => $action,
            'target_id' => $targetId !== null ? (string) $targetId : null, 'details' => $details,
            'ip_address' => service('request')->getIPAddress(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
if (! function_exists('forget_site_cache')) {
    function forget_site_cache(): void
    {
        cache()->delete('menu_cats');
        cache()->delete('footer_pages');
    }
}
