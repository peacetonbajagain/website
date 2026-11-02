<?php

use CodeIgniter\I18n\Time;
use Travo\Admin\Models\PostModel;
use Config\Services;


use Travo\Admin\Models\ActivityLogModel;


if (!function_exists('humanize_date')) {
    function humanize_date($date)
    {
        if (!$date)
            return '';
        return Time::parse($date)->format('Y-m-d H:i');
    }
}


if (!function_exists('json_attr')) {
    /**
     * Encode data as a JSON string that is safe to embed inside an HTML
     * attribute (e.g. x-data="..."). Uses HTML entities for quotes so the
     * browser decodes them back to raw quotes before Alpine parses the
     * expression — unlike esc($x, 'js') which emits \xHH escapes that are
     * only valid inside JS string literals and break expression parsing.
     */
    function json_attr($data): string
    {
        return htmlspecialchars(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


if (!function_exists('get_categories_with_children')) {
    function get_categories_with_children()
    {
        static $db = null;
        if ($db === null) {
            $db = db_connect();
        }

        // Fetch only top-level categories (parent_id = 0)
        $builder = $db->table('categories');
        $categories = $builder
            ->where('status', 1)
            ->where('parent_id', 0)
            ->orderBy('created_at', 'desc')
            ->get()
            ->getResultArray();

        foreach ($categories as &$category) {
            $category['children'] = sub_categories_helper($category['id'], $db);
        }

        return $categories;
    }
}

if (!function_exists('sub_categories_helper')) {
    function sub_categories_helper($parent_id, $db)
    {
        $builder = $db->table('categories');
        $categories = $builder
            ->where('status', 1)
            ->where('parent_id', $parent_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->getResultArray();

        foreach ($categories as &$category) {
            $category['children'] = sub_categories_helper($category['id'], $db);
        }

        return $categories;
    }
}




// Get Post Data
if (!function_exists('get_post_data')) {
    function get_post_data($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            return '';
        }

        // Load model only once
        static $postmodel = null;
        $postmodel ??= new \Travo\Admin\Models\PostModel();

        // Fetch Post from the database
        $post = $postmodel->find($id);
        return $post ?: '';
    }
}

// Get Post Comments
if (!function_exists('get_comment_count')) {
    function get_comment_count($id)
    {
        static $commentModel = null;
        if ($commentModel === null) {
            $commentModel = new \Travo\Admin\Models\CommentModel();
        }
        return $commentModel->countComment($id);
    }
}



if (!function_exists('getSegment')) {
    function getSegment(int $number, string $default = '')
    {
        $request = \Config\Services::request();
        $uri = $request->getUri()->setSilent();
        return $uri->getSegment($number, $default);
    }
}


if (!function_exists('create_unique_slug')) {
    function create_unique_slug($string, $table, $id = null)
    {
        // First, clean the string using the SEO-friendly helper
        $cleanString = clean_seo_string($string);

        // Then convert the cleaned string into a URL-friendly slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $cleanString), '-'));

        // Connect to the database
        $db = \Config\Database::connect();
        $builder = $db->table($table);

        // Prepare parameters to check for existing slugs
        $params = ['slug' => $slug];

        // Exclude the current record from the check if an ID is provided
        if ($id) {
            $params['id !='] = $id;
        }

        // Check if the slug already exists in the database
        $i = 0;
        while ($builder->where($params)->countAllResults()) {
            // If a duplicate exists, increment the number at the end of the slug
            if (!preg_match('/-{1}[0-9]+$/', $slug)) {
                $slug .= '-' . ++$i;
            } else {
                $slug = preg_replace('/[0-9]+$/', ++$i, $slug);
            }

            // Update the params with the new slug
            $params['slug'] = $slug;
        }

        // Return the unique slug
        return $slug;
    }
}


if (!function_exists('full_current_url')) {
    function full_current_url()
    {
        $current_url = current_url();
        $query_string = $_SERVER['QUERY_STRING'] ?? '';
        return $current_url . ($query_string ? '?' . $query_string : '');
    }
}


if (!function_exists('has_error')) {
    function has_error(string $field): bool
    {
        return isset(session('errors')[$field]);
    }
}

if (!function_exists('error')) {
    function error(string $field)
    {
        return session('errors')[$field] ?? '';
    }
}


if (!function_exists('app_date')) {
    function app_date($date, bool $includeTime = false, bool $includeTimezone = false): string
    {
        $format = $includeTime
            ? [setting('App.dateFormat') ?? 'Y-m-d', setting('App.timeFormat') ?? 'H:i:s', $includeTimezone ? 'T' : '']
            : [setting('App.dateFormat') ?? 'Y-m-d', $includeTimezone ? 'T' : ''];

        $format = trim(implode(' ', $format));

        if (is_string($date)) {
            $date = Time::parse($date);
        }

        $date->setTimezone(setting('App.appTimezone'));
        return $date->format($format);
    }
}

if (!function_exists('site_offline')) {
    function site_offline(): bool
    {
        return empty(setting('Site.siteOnline'));
    }
}

if (!function_exists('get_device_type')) {
    function get_device_type($user_agent)
    {
        $mobile_regex = '/(?:phone|windows\s+phone|ipod|blackberry|(?:android|bb\d+|meego|silk|googlebot) .+? mobile|palm|windows\s+ce|opera mini|avantgo|mobilesafari|docomo)/i';
        $tablet_regex = '/(?:ipad|playbook|(?:android|bb\d+|meego|silk)(?! .+? mobile))/i';

        if (preg_match($mobile_regex, $user_agent)) {
            return 'mobile';
        } elseif (preg_match($tablet_regex, $user_agent)) {
            return 'tablet';
        } else {
            return 'desktop';
        }
    }
}

if (!function_exists('user')) {
    function user()
    {
        return auth()->user();
    }
}



if (!function_exists('log_activity')) {
    function log_activity($action, $module, $targetId = null, $details = null)
    {
        try {
            $logModel = new \Travo\Admin\Models\ActivityLogModel();

            $user = auth()->user();
            $userId = $user ? $user->id : 0;
            $userName = $user ? $user->username : 'Guest';

            // If guest, maybe append session ID for tracking
            if ($userId === 0) {
                $userName .= ' (' . substr(session_id(), 0, 8) . ')';
            }

            $payload = [
                'description' => is_string($details) ? $details : ($action . ' ' . $module),
                'data' => is_array($details) ? $details : null,
                'user_agent' => service('request')->getUserAgent()->getAgentString()
            ];

            // Logging must NEVER break the main action.
            $logModel->insert([
                'user_id' => $userId,
                'user_name' => $userName,
                'action' => $action,
                'module' => $module,
                'target_id' => $targetId,
                'details' => json_encode($payload),
                'ip_address' => service('request')->getIPAddress(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Activity log failed (' . $module . '/' . $action . '): ' . $e->getMessage());
        }
    }
}

/**
 * Optimized SEO String Helper
 * - Transliterates UTF-8 (accents/special chars)
 * - Removes 100+ stop words (O(1) lookup speed)
 * - Strips listicle prefixes (e.g., "10-best" -> "best")
 * - Maintains natural word order (vital for SEO context)
 *
 * @param string $string Input title
 * @param int $limit Max words to keep
 * @param array $customStopWords Additional words to filter
 * @return string Optimized hyphenated slug
 */
function clean_seo_string($string, $limit = 9, $customStopWords = [])
{
    if (empty($string))
        return '';

    // 1. Lowercase and Transliterate (e.g., "Café" -> "cafe")
    // Uses 'intl' if available, otherwise fallback to basic iconv
    if (extension_loaded('intl')) {
        $string = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $string);
    } else {
        $string = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $string));
    }

    // 2. Remove numeric listicle prefixes (e.g., "12. " or "5-")
    $string = preg_replace('/^\d+[\.\-\s]+/', '', $string);

    // 3. Comprehensive SEO Stop Words (Flipped for O(1) performance)
    $stopWords = array_flip(array_merge([
        'a',
        'an',
        'the',
        'and',
        'or',
        'but',
        'is',
        'are',
        'was',
        'were',
        'be',
        'been',
        'being',
        'in',
        'on',
        'at',
        'by',
        'for',
        'with',
        'about',
        'against',
        'between',
        'into',
        'through',
        'during',
        'before',
        'after',
        'above',
        'below',
        'to',
        'from',
        'up',
        'down',
        'out',
        'off',
        'over',
        'under',
        'again',
        'further',
        'then',
        'once',
        'here',
        'there',
        'when',
        'where',
        'why',
        'how',
        'all',
        'any',
        'both',
        'each',
        'few',
        'more',
        'most',
        'other',
        'some',
        'such',
        'no',
        'nor',
        'not',
        'only',
        'own',
        'same',
        'so',
        'than',
        'too',
        'very',
        'can',
        'will',
        'just',
        'should',
        'now',
        'has',
        'have',
        'had',
        'do',
        'does',
        'did',
        'done',
        'your',
        'my',
        'i',
        'me',
        'we',
        'us',
        'he',
        'she',
        'it',
        'they',
        'them',
        'their',
        'ours',
        'yours',
        'what',
        'which',
        'who',
        'whom',
        'whose',
        'am',
        'might',
        'must',
        'shall',
        'may'
    ], $customStopWords));

    // 4. Split into words and clean non-alphanumeric
    $words = preg_split('/[^a-z0-9]+/', $string, -1, PREG_SPLIT_NO_EMPTY);
    $cleanWords = [];

    foreach ($words as $word) {
        // Skip stop words and single characters (except numbers like '5g')
        if (isset($stopWords[$word]) || (strlen($word) < 2 && !is_numeric($word))) {
            continue;
        }

        $cleanWords[] = $word;

        // Stop once we hit the word limit to save processing
        if (count($cleanWords) >= $limit)
            break;
    }

    // 5. Fallback: If title was all stop words, use the first few original words
    if (empty($cleanWords)) {
        $cleanWords = array_slice($words, 0, $limit);
    }

    return implode('-', $cleanWords);
}