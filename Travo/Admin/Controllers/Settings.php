<?php

namespace Travo\Admin\Controllers;
use CodeIgniter\Controller;
use DateTimeZone;

class Settings extends Controller
{

    public function __construct()
    {
        helper(['form', 'base']);
    }

    public function index()
    {
        $authGroups = config('AuthGroups');
        $groups = $authGroups->groups;
        $permissions = $authGroups->permissions;
        $matrix = setting('AuthGroups.matrix') ?? $authGroups->matrix;
        $permissionMatrix = [];

        foreach ($groups as $group => $details) {
            foreach ($permissions as $permission => $description) {
                $permissionMatrix[$group][$permission] = $this->groupHasPermission($matrix[$group] ?? [], $permission);
            }
        }

        return view('Travo\Admin\Views\Settings\index', [
            'settings' => [
                'siteName' => setting('Travo.siteName') ?: setting('App.siteName') ?: 'News Portal',
                'tagline' => setting('Travo.tagline') ?: '',
                'description' => setting('Travo.description') ?: '',
                'contactEmail' => setting('Travo.contactEmail') ?: '',
                'postsPerPage' => setting('Travo.postsPerPage') ?: 12,
                'allowRegistration' => (bool) (setting('Auth.allowRegistration') ?? config('Auth')->allowRegistration),
                'commentsEnabled' => (bool) (setting('Travo.commentsEnabled') ?? true),
            ],
            'canManagePermissions' => auth()->user()?->inGroup('superadmin') ?? false,
            'groups' => $groups,
            'permissions' => $permissions,
            'permissionMatrix' => $permissionMatrix,
        ]);
    }
    public function users()
    {
        $rememberOptions = [
            '1 hour' => 1 * HOUR,
            '4 hours' => 4 * HOUR,
            '8 hours' => 8 * HOUR,
            '25 hours' => 24 * HOUR,
            '1 week' => 1 * WEEK,
            '2 weeks' => 2 * WEEK,
            '3 weeks' => 3 * WEEK,
            '1 month' => 1 * MONTH,
            '2 months' => 2 * MONTH,
            '6 months' => 6 * MONTH,
            '1 year' => 12 * MONTH,
        ];

        return view('Travo\Admin\Views\Settings\user', [
            'rememberOptions' => $rememberOptions,
            'defaultGroup' => setting('AuthGroups.defaultGroup'),
            'groups' => setting('AuthGroups.groups'),
        ]);
    }

    /**
     * Saves the email settings to the config file, where it
     * is automatically saved by our dynamic configuration system.
     */
    public function save()
    {
        if ($this->request->getPost('site_name') !== null) {
            $rules = [
                'site_name' => 'required|min_length[2]|max_length[100]',
                'site_tagline' => 'permit_empty|max_length[160]',
                'site_description' => 'permit_empty|max_length[300]',
                'contact_email' => 'permit_empty|valid_email|max_length[254]',
                'posts_per_page' => 'required|integer|greater_than[0]|less_than_equal_to[50]',
            ];
            if (! $this->validate($rules)) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }

            setting('Travo.siteName', trim((string) $this->request->getPost('site_name')));
            setting('Travo.tagline', trim((string) $this->request->getPost('site_tagline')));
            setting('Travo.description', trim((string) $this->request->getPost('site_description')));
            setting('Travo.contactEmail', trim((string) $this->request->getPost('contact_email')));
            setting('Travo.postsPerPage', (int) $this->request->getPost('posts_per_page'));
            setting('Travo.commentsEnabled', $this->request->getPost('comments_enabled') === '1');
            setting('Auth.allowRegistration', $this->request->getPost('allow_registration') === '1');

            return redirect()->to('admin/settings')->with('message', 'Settings saved.');
        }

        $knownGroups = array_keys(setting('AuthGroups.groups') ?? []);
        $rules = [
            'minimumPasswordLength' => 'required|integer|greater_than[6]',
            'defaultGroup' => 'required|string|in_list[' . implode(',', $knownGroups) . ']',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        setting('Auth.allowRegistration', (bool) $this->request->getPost('allowRegistration'));
        setting('Auth.minimumPasswordLength', (int) $this->request->getPost('minimumPasswordLength'));

        // Whitelist validators against the classes Shield actually knows.
        $validValidators = config('Auth')->passwordValidators ?? [];
        $postedValidators = (array) ($this->request->getPost('validators') ?? []);
        setting('Auth.passwordValidators', array_values(array_intersect($postedValidators, $validValidators)));

        // Never allow an unknown/empty default group to lock new users out.
        $defaultGroup = $this->request->getPost('defaultGroup');
        setting('AuthGroups.defaultGroup', \in_array($defaultGroup, $knownGroups, true) ? $defaultGroup : 'user');

        // Actions — only real Shield action classes may ever be stored.
        $actions = setting('Auth.actions') ?? [];
        foreach (['login' => 'email2FA', 'register' => 'emailActivation'] as $action => $field) {
            $posted = $this->request->getPost($field);
            if ($posted === null || $posted === '') {
                $actions[$action] = null;
            } elseif (
                \is_string($posted)
                && class_exists($posted)
                && is_subclass_of($posted, \CodeIgniter\Shield\Authentication\Actions\ActionInterface::class)
            ) {
                $actions[$action] = $posted;
            }
        }
        setting('Auth.actions', $actions);

        // Remember Me
        $sessionConfig = setting('Auth.sessionConfig');
        $sessionConfig['allowRemembering'] = $this->request->getPost('allowRemember') ?? false;
        $rememberLength = $this->request->getPost('rememberLength');
        $sessionConfig['rememberLength'] = is_numeric($rememberLength) ? (int) $rememberLength : ($sessionConfig['rememberLength'] ?? 0);
        setting('Auth.sessionConfig', $sessionConfig);
        return redirect()->back();
    }

    public function updatePermissions()
    {
        if (auth()->user()?->inGroup('superadmin') !== true) {
            return redirect()->to('admin/settings')->with('error', lang('notAuthorized'));
        }

        $authGroups = config('AuthGroups');
        $matrix = setting('AuthGroups.matrix') ?? $authGroups->matrix;
        $submitted = (array) ($this->request->getPost('permissions') ?? []);
        foreach ($authGroups->groups as $group => $details) {
            if ($group === 'superadmin') {
                continue;
            }
            $selected = array_intersect((array) ($submitted[$group] ?? []), array_keys($authGroups->permissions));
            $matrix[$group] = array_values($selected);
        }
        setting('AuthGroups.matrix', $matrix);

        return redirect()->to('admin/settings')->with('message', 'Role permissions saved.');
    }

    private function groupHasPermission(array $granted, string $permission): bool
    {
        foreach ($granted as $pattern) {
            if ($pattern === $permission) {
                return true;
            }
            if (str_ends_with($pattern, '.*') && str_starts_with($permission, substr($pattern, 0, -1))) {
                return true;
            }
        }

        return false;
    }


    public function email()
    {
        return $this->index();
    }

    /**
     * Saves the email settings to the config file, where it
     * is automatically saved by our dynamic configuration system.
     */
    public function emailsave()
    {
        $rules = [
            'fromName' => 'required|string|min_length[2]',
            'fromEmail' => 'required|valid_email',
            'protocol' => 'required|in_list[mail,sendmail,smtp]',
            'mailPath' => 'permit_empty|string',
            'SMTPHost' => 'permit_empty|string',
            'SMTPPort' => 'permit_empty|in_list[25,587,465,2525,other]',
            'SMTPPortOther' => 'permit_empty|string',
            'SMTPUser' => 'permit_empty|string',
            'SMTPPass' => 'permit_empty|string',
            'SMTPCrypto' => 'permit_empty|in_list[ssl,tls]',
            'SMTPTimeout' => 'permit_empty|integer|greater_than_equal_to[0]',
            'SMTPKeepAlive' => 'permit_empty|in_list[0,1]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $port = $this->request->getPost('SMTPPort') === 'other'
            ? $this->request->getPost('SMTPPortOther')
            : $this->request->getPost('SMTPPort');

        setting('Email.fromName', $this->request->getPost('fromName'));
        setting('Email.fromEmail', $this->request->getPost('fromEmail'));
        setting('Email.protocol', $this->request->getPost('protocol'));
        setting('Email.mailPath', $this->request->getPost('mailPath'));
        setting('Email.SMTPHost', $this->request->getPost('SMTPHost'));
        setting('Email.SMTPPort', $port);
        setting('Email.SMTPUser', $this->request->getPost('SMTPUser'));
        setting('Email.SMTPPass', $this->request->getPost('SMTPPass'));
        setting('Email.SMTPCrypto', $this->request->getPost('SMTPCrypto'));
        setting('Email.SMTPTimeout', $this->request->getPost('SMTPTimeout'));
        setting('Email.SMTPKeepAlive', $this->request->getPost('SMTPKeepAlive'));

        // alert('success', 'The settings have been saved.');

        return redirect()->back();
    }

    public function testEmail()
    {
        $to = $this->request->getPost('testEmail');

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->with('error', 'Invalid email address provided.');
        }

        // --- Network Diagnostics ---

        // Helper to get request value or setting
        $getValue = function ($key) {
            return $this->request->getPost($key) ?: setting('Email.' . $key);
        };

        $protocol = $getValue('protocol');
        $host = $getValue('SMTPHost');
        $port = (int) $getValue('SMTPPort');
        $crypto = $getValue('SMTPCrypto');

        // If protocol is not SMTP, some diagnostics might not apply, but let's keep it simple for now as per user context
        if ($protocol === 'smtp') {
            // 0. Never allow internal/loopback/private targets (SSRF guard)
            if ($host) {
                $hostIp = filter_var($host, FILTER_VALIDATE_IP)
                    ? $host
                    : (gethostbyname($host) ?: '');

                if (
                    $hostIp === false || $hostIp === ''
                    || filter_var($hostIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
                ) {
                    return redirect()->back()->with('error', 'Invalid SMTP host: private or loopback addresses are not allowed.');
                }
            }

            // 1. Check DNS
            if ($host && filter_var($host, FILTER_VALIDATE_IP) === false) {
                // It's a hostname, resolve it
                $ip = gethostbyname($host);
                // If gethostbyname returns the hostname itself, resolution failed
                if ($ip === $host) {
                    return redirect()->back()->with('error', "DNS Error: Could not resolve hostname '" . esc($host) . "'. Please check your internet connection.");
                }
            }

            // 2. Check Reachability (Timeout 10s)
            if ($host && $port) {
                $connectHost = ($crypto === 'ssl' ? 'ssl://' : '') . $host;
                $fp = @fsockopen($connectHost, $port, $errno, $errstr, 10);
                if (!$fp) {
                    return redirect()->back()->with('error', "Connection Error: Could not connect to " . esc($connectHost) . " on port $port. Error $errno: " . esc($errstr) . " (Likely blocked by Firewall/ISP)");
                }
                fclose($fp);
            }
        }

        // --- End Diagnostics ---

        $email = \Config\Services::email();
        $overrides = [
            'protocol' => $getValue('protocol'),
            'SMTPHost' => $getValue('SMTPHost'),
            'SMTPPort' => (int) $getValue('SMTPPort'),
            'SMTPUser' => $getValue('SMTPUser'),
            'SMTPPass' => $getValue('SMTPPass'),
            'SMTPCrypto' => $getValue('SMTPCrypto'),
            'mailType' => 'html',
            'SMTPTimeout' => (int) $getValue('SMTPTimeout') > 0 ? (int) $getValue('SMTPTimeout') : 30,
            'fromEmail' => $getValue('fromEmail'),
            'fromName' => $getValue('fromName'),
        ];

        $email->initialize($overrides);

        $email->setFrom($overrides['fromEmail'] ?? 'no-reply@example.com', $overrides['fromName'] ?? 'System Test');
        $email->setTo($to);
        $email->setSubject('Test Email from ' . setting('Site.siteName'));
        $email->setMessage('<h1>Test Successful!</h1><p>Your email settings are configured correctly.</p><p>Sent at: ' . date('Y-m-d H:i:s') . '</p>');

        if ($email->send()) {
            return redirect()->back()->with('message', 'Test email sent successfully to ' . $to);
        } else {
            $data = $email->printDebugger(['headers']);
            return redirect()->back()->with('error', 'Failed to send email. Debug: ' . $data);
        }
    }

    public function general()
    {
        if (!auth()->user()->can('admin.settings')) {
            return redirect()->to('admin/dashboard')->with('error', lang('notAuthorized'));
        }

        $timezoneAreas = [];

        foreach (timezone_identifiers_list() as $timezone) {
            if (strpos($timezone, '/') === false) {
                $timezoneAreas[] = $timezone;

                continue;
            }

            [$area] = explode('/', $timezone, 2);
            if (!\in_array($area, $timezoneAreas, true)) {
                $timezoneAreas[] = $area;
            }
        }

        $currentTZ = setting('App.appTimezone');
        $currentTZArea = strpos($currentTZ, '/') === false
            ? $currentTZ
            : substr($currentTZ, 0, strpos($currentTZ, '/'));

        echo view('Travo\Admin\Views\Settings\general', [
            'timezones' => $timezoneAreas,
            'currentTZArea' => $currentTZArea,
            'timezoneOptions' => $this->getTimezones($currentTZArea),
            'dateFormat' => setting('App.dateFormat') ?: 'M j, Y',
            'timeFormat' => setting('App.timeFormat') ?: 'g:i A',
        ]);
    }

    /**
     * Saves the general settings
     *
     */
    public function saveGeneral()
    {
        if (!auth()->user()->can('admin.settings')) {
            return redirect()->to('admin/dashboard')->with('error', lang('notAuthorized'));
        }

        $rules = [
            'siteName' => 'required|string',
            'timezone' => 'required|string',
            'dateFormat' => 'required|string',
            'timeFormat' => 'required|string',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        setting('Site.siteName', $this->request->getPost('siteName'));
        setting('Site.siteOnline', (bool) $this->request->getPost('siteOnline'));
        $logoRaw = $this->request->getPost('logo');
        $logoId = null;
        if ($logoRaw !== null && $logoRaw !== '' && ctype_digit((string)trim($logoRaw))) {
            $logoId = (int)trim($logoRaw);
            $logoId = $logoId > 0 ? $logoId : null;
        }
        setting('Site.logo', $logoId);

        // Contact details (used by footer + contact page)
        setting('Site.contactEmail', trim((string) $this->request->getPost('contactEmail')) ?: null);
        setting('Site.contactPhone', trim((string) $this->request->getPost('contactPhone')) ?: null);
        setting('Site.contactAddress', trim((string) $this->request->getPost('contactAddress')) ?: null);

        setting('App.appTimezone', $this->request->getPost('timezone'));

        setting('App.dateFormat', $this->request->getPost('dateFormat'));
        setting('App.timeFormat', $this->request->getPost('timeFormat'));

        setting('App.enableComments', (bool) $this->request->getPost('enableComments'));

        return redirect()->to('admin/settings/general')->with('message', lang('resourcesSaved', ['settings']));
    }
    /**
     * AJAX method to list available timezones within
     * a single timezone area  (AMERICA, AFRICA, etc)
     */
    public function getTimezones(?string $area = null): string
    {
        $area ??= $this->request->getVar('timezoneArea');
        $ids = [
            'Africa' => DateTimeZone::AFRICA,
            'America' => DateTimeZone::AMERICA,
            'Antarctica' => DateTimeZone::ANTARCTICA,
            'Arctic' => DateTimeZone::ARCTIC,
            'Asia' => DateTimeZone::ASIA,
            'Atlantic' => DateTimeZone::ATLANTIC,
            'Australia' => DateTimeZone::AUSTRALIA,
            'Europe' => DateTimeZone::EUROPE,
            'Indian' => DateTimeZone::INDIAN,
            'Pacific' => DateTimeZone::PACIFIC,
        ];

        $options = [];

        if ($area === 'UTC') {
            $options['UTC'] = 'UTC';
        } else {
            foreach (timezone_identifiers_list($ids[$area]) as $timezone) {
                $formattedTimezone = str_replace('_', ' ', $timezone);
                $formattedTimezone = str_replace($area . '/', '', $formattedTimezone);
                $options[$timezone] = $formattedTimezone;
            }
        }

        return view('Travo\Admin\Views\Settings\_timezones', [
            'options' => $options,
            'selectedTZ' => setting('App.appTimezone'),
        ]);
    }
    public function clearCache()
    {
        helper('filesystem');
        $staticPageCache = new \Travo\FrontEnd\Controllers\LIbraries\StaticPageCache();
        $staticPageCache->purge();
        $cachePath = WRITEPATH . 'cache/';
        if (delete_files($cachePath, true)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['status' => 'success', 'message' => 'All Cache Cleared Successfully']);
            }
            return redirect()->to('admin/dashboard')->with('message', 'All Cache Cleared Successfully');
        } else {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to clear cache.']);
            }
            return redirect()->back()->with('error', 'Failed to clear cache.');
        }
    }

    public function homepage()
    {
        $catModel = new \Travo\Admin\Models\PostCategoryModel();
        $layoutJson = setting('Homepage.layout');
        $layout = $layoutJson ? \Travo\Admin\Libraries\HomepageLayout::normalizeLayout(json_decode($layoutJson, true)) : [];

        if (empty($layout)) {
            $layout = \Travo\Admin\Libraries\HomepageLayout::defaultLayout();
        }
        $canonical = json_encode($layout, JSON_UNESCAPED_UNICODE);
        if ($canonical !== (string) $layoutJson) {
            setting('Homepage.layout', $canonical);
        }

        $categories = $catModel->where('status', 1)->orderBy('title', 'ASC')->findAll();

        // Live published-post count per category slug (for the builder dropdown + warnings)
        $postCounts = $catModel->getPostCounts();
        $catCounts = [];
        foreach ($categories as $c) {
            $catCounts[$c['slug']] = (int) ($postCounts[(int) $c['id']] ?? 0);
        }

        // Active ads (legacy placeholder)
        $ads = [];

        return view('Travo\Admin\Views\Settings\homepage', [
            'layout'         => $layout,
            'categories'     => $categories,
            'cat_counts'     => $catCounts,
            'ads'            => $ads,
            'default_layout' => \Travo\Admin\Libraries\HomepageLayout::defaultLayout(),
            'has_backup'     => !empty(setting('Homepage.layout_backup')),
        ]);
    }

    public function seo()
    {
        if (!auth()->user()->can('admin.settings')) {
            return redirect()->to('admin/dashboard')->with('error', lang('notAuthorized'));
        }

        return view('Travo\Admin\Views\Settings\seo', [
            'faviconExists' => is_file(FCPATH . 'uploads/static/favicon-96x96.png'),
            'faviconUrl'    => base_url('uploads/static/favicon-96x96.png'),
        ]);
    }

    public function saveSeo()
    {
        if (!auth()->user()->can('admin.settings')) {
            return redirect()->to('admin/dashboard')->with('error', lang('notAuthorized'));
        }

        // Meta data
        setting('App.siteMetaDesc', trim((string) $this->request->getPost('siteMetaDesc')) ?: null);
        setting('App.siteMetaKeywords', trim((string) $this->request->getPost('siteMetaKeywords')) ?: null);

        // Open Graph / share image (media ID)
        $ogRaw = $this->request->getPost('siteMetaImage');
        $ogId = null;
        if ($ogRaw !== null && $ogRaw !== '' && ctype_digit((string) trim($ogRaw))) {
            $ogId = (int) trim($ogRaw);
            $ogId = $ogId > 0 ? $ogId : null;
        }
        setting('App.siteMetaImage', $ogId);

        // Social media links (validated URLs only)
        foreach (['facebook', 'twitter_x', 'youtube', 'instagram', 'tiktok'] as $key) {
            $url = trim((string) $this->request->getPost('social_' . $key));
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                setting('Social.' . $key, $url);
            } else {
                setting('Social.' . $key, null);
            }
        }

        // Footer company links — only checked slugs are shown in the footer.
        $allowedCompany = ['advertisement', 'about-us', 'contact-us', 'privacy-policy', 'terms-of-service'];
        $checked = (array) $this->request->getPost('footer_links');
        $enabled = array_values(array_intersect($allowedCompany, array_map('strval', $checked)));
        setting('Footer.companyLinks', json_encode($enabled, JSON_UNESCAPED_UNICODE));

        $this->purgePageCache();

        return redirect()->to('admin/settings/seo')->with('message', 'SEO and branding settings saved successfully.');
    }

    public function theme()
    {
        if (!auth()->user()->can('admin.settings')) {
            return redirect()->to('admin/dashboard')->with('error', lang('notAuthorized'));
        }

        return view('Travo\Admin\Views\Settings\theme', [
            'primary'   => setting('Theme.primary') ?: '#dd1419',
            'secondary' => setting('Theme.secondary') ?: '#0464A4',
            'accent'    => setting('Theme.accent') ?: '#04385f',
        ]);
    }

    public function saveTheme()
    {
        if (!auth()->user()->can('admin.settings')) {
            return redirect()->to('admin/dashboard')->with('error', lang('notAuthorized'));
        }

        $rules = [
            'primary'   => 'permit_empty|regex_match[/^#[0-9a-fA-F]{6}$/]',
            'secondary' => 'permit_empty|regex_match[/^#[0-9a-fA-F]{6}$/]',
            'accent'    => 'permit_empty|regex_match[/^#[0-9a-fA-F]{6}$/]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        setting('Theme.primary', $this->request->getPost('primary') ?: '#dd1419');
        setting('Theme.secondary', $this->request->getPost('secondary') ?: '#0464A4');
        setting('Theme.accent', $this->request->getPost('accent') ?: '#04385f');

        $this->clearCache();

        return redirect()->to('admin/settings/theme')->with('message', lang('resourcesSaved', ['settings']));
    }

    public function menu()
    {
        if (!auth()->user()->can('admin.settings')) {
            return redirect()->to('admin/dashboard')->with('error', lang('notAuthorized'));
        }

        $catModel = new \Travo\Admin\Models\PostCategoryModel();
        $pageModel = new \Travo\Admin\Models\PageModel();

        $menuJson = setting('Menu.main');
        $menu = $menuJson ? json_decode($menuJson, true) : [];

        return view('Travo\Admin\Views\Settings\menu', [
            'menu'       => \is_array($menu) ? $menu : [],
            'categories' => $catModel->where('status', 1)->orderBy('sort_order', 'ASC')->orderBy('title', 'ASC')->findAll(),
            'pages'      => $pageModel->where('status', 1)->orderBy('title', 'ASC')->findAll(),
        ]);
    }

    public function saveMenu()
    {
        if (!auth()->user()->can('admin.settings')) {
            return redirect()->to('admin/dashboard')->with('error', lang('notAuthorized'));
        }

        $data = $this->request->getPost('menu_data');
        if ($data) {
            $decoded = json_decode($data, true);
            if (!\is_array($decoded)) {
                return redirect()->to('admin/settings/menu')->with('error', 'Invalid menu data');
            }
            setting('Menu.main', $data);
            $this->clearCache();
            return redirect()->to('admin/settings/menu')->with('message', 'Menu saved successfully.');
        }

        return redirect()->to('admin/settings/menu')->with('error', 'No menu data received');
    }

    public function saveHomepage()
    {
        $data = $this->request->getPost('layout_data');

        // Keep a one-step undo backup before any overwrite (even a default reset).
        $previous = setting('Homepage.layout');

        if (!$data || !\is_array(json_decode($data, true))) {
            // Design-loss protection: never leave the homepage broken.
            // Reset to the canonical defaults and persist them correctly.
            $defaults = \Travo\Admin\Libraries\HomepageLayout::defaultLayoutJson();
            if ($previous && $previous !== $defaults) {
                setting('Homepage.layout_backup', $previous);
            }
            setting('Homepage.layout', $defaults);
            setting('Homepage.dedup', $this->request->getPost('dedup') === '1' ? '1' : '0');
            $this->purgePageCache();

            return redirect()->to('admin/settings/homepage')
                ->with('error', 'Layout data was empty or invalid — default layout was automatically restored.');
        }

        // Only accept valid JSON — garbage must never wipe the saved layout
        $decoded = json_decode($data, true);

        if ($previous && $previous !== $data) {
            setting('Homepage.layout_backup', $previous);
        }

        // Sanitize/normalize before saving so invalid sections can't break the page
        $clean = \Travo\Admin\Libraries\HomepageLayout::sanitizeSections(\is_array($decoded) ? $decoded : []);
        setting('Homepage.layout', json_encode($clean, JSON_UNESCAPED_UNICODE));

        // Global dedup toggle (default ON)
        setting('Homepage.dedup', $this->request->getPost('dedup') === '1' ? '1' : '0');

        $this->purgePageCache();

        return redirect()->to('admin/settings/homepage')->with('message', 'Homepage layout saved successfully');
    }

    public function restoreHomepage()
    {
        $backup = setting('Homepage.layout_backup');
        if (!$backup) {
            return redirect()->to('admin/settings/homepage')->with('error', 'No backup available.');
        }

        $decoded = json_decode($backup, true);
        $restored = \Travo\Admin\Libraries\HomepageLayout::normalizeLayout(\is_array($decoded) ? $decoded : []);
        if (empty($restored)) {
            return redirect()->to('admin/settings/homepage')->with('error', 'Backup data is invalid.');
        }

        $previous = setting('Homepage.layout');
        setting('Homepage.layout_backup', $previous); // allow toggling back
        setting('Homepage.layout', json_encode($restored, JSON_UNESCAPED_UNICODE));

        $this->purgePageCache();

        return redirect()->to('admin/settings/homepage')->with('message', 'Previous saved layout restored successfully.');
    }

    /**
     * Purge static page caches + driver caches (used after any layout change).
     */
    private function purgePageCache(): void
    {
        helper('filesystem');
        $staticPageCache = new \Travo\FrontEnd\Controllers\LIbraries\StaticPageCache();
        $staticPageCache->purge();
        delete_files(WRITEPATH . 'cache/', false);
        \Config\Services::cache()->clean();
    }
}
