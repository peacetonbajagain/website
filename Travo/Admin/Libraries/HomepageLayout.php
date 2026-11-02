<?php

namespace Travo\Admin\Libraries;

/**
 * Single source of truth for the homepage layout.
 *
 * Canonical schema — a section IS a ROW of up to 3 columns. Each column holds
 * one widget. Every section can have a different layout (1/2/3 columns) and
 * every column can be a different widget:
 *
 *   [
 *     { "id":"r1", "type":"section_row", "title":"Glasses Lifestyle",
 *       "layout_mode":"3-col-543", "bg_style":"accent-gradient",
 *       "columns":[
 *         { "span":"lg:col-span-5", "widgets":[ { "type":"card_featured_hero", "slug":"specs-lifestyle" } ] },
 *         { "span":"lg:col-span-4", "widgets":[ { "type":"card_news_list", "slug":"specs-lifestyle", "title":"More Stories", "count":5 } ] },
 *         { "span":"lg:col-span-3", "widgets":[ { "type":"card_events_widget", "count":4 } ] }
 *       ] },
 *     { "type":"section_row", "title":"", "layout_mode":"1-col", "bg_style":"transparent",
 *       "columns":[ { "span":"lg:col-span-12", "widgets":[ { "type":"card_ad_banner", "label":"Sponsored", "height":90 } ] } ] }
 *   ]
 *
 * Layout modes map to column spans:
 *   1-col      -> 12
 *   2-col-75   -> 7 | 5
 *   2-col-66   -> 6 | 6
 *   3-col-543  -> 5 | 4 | 3   (default "hub")
 *   3-col-444  -> 4 | 4 | 4
 *
 * Category widgets always pull their OWN category's newest posts only
 * (enforced in the renderer) — no cross-category filling.
 *
 * Also migrates the flat single-widget schema (v1) and the legacy native
 * section objects transparently.
 */
class HomepageLayout
{
    public const LAYOUT_MODES = ['1-col', '2-col-75', '2-col-66', '3-col-543', '3-col-444'];
    public const BG_STYLES = ['transparent', 'accent-gradient', 'surface-card'];
    public const WIDGET_TYPES = [
        'card_featured_hero', 'card_news_list', 'card_news_grid',
        'featured_stack', 'card_multimedia_video', 'card_events_widget', 'card_ad_banner',
        'card_opinion',
    ];
    public const GRID_STYLES = ['grid_standard', 'grid_hero_lead', 'grid_lead_split', 'grid_compact_list', 'grid_travel'];

    /** Flat v1 section types (single-widget sections). */
    private const FLAT_TYPES = ['featured', 'category', 'video', 'ad', 'events'];
    private const FLAT_STYLES = ['hub', 'hero_list', 'big_grid', 'grid', 'compact'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaultLayout(): array
    {
        return [
            [
                'type' => 'section_row', 'title' => '', 'layout_mode' => '1-col', 'bg_style' => 'transparent',
                'columns' => [['span' => 'lg:col-span-12', 'widgets' => [['type' => 'featured_stack', 'count' => 3]]]],
            ],
            self::hubRow('Glasses Lifestyle', 'specs-lifestyle', 'accent-gradient', 'events'),
            self::hubRow('Fog Chronicles', 'fog-chronicles', 'transparent', 'list'),
            [
                'type' => 'section_row', 'title' => 'Eyewear Videos', 'layout_mode' => '1-col', 'bg_style' => 'transparent',
                'columns' => [['span' => 'lg:col-span-12', 'widgets' => [['type' => 'card_multimedia_video', 'title' => 'Eyewear Videos', 'count' => 5]]]],
            ],
            self::hubRow('Lens Care & Tips', 'lens-care', 'transparent', 'list'),
            self::opinionRow('Community Stories', 'community-stories'),
            self::hubRow('Frame Trends', 'frame-trends', 'transparent', 'list'),
            self::hubRow('Eyewear Humor', 'eyewear-humor', 'transparent', 'list'),
        ];
    }

    /**
     * An opinion / editorial section — columnist/community-led layout.
     */
    private static function opinionRow(string $title, string $slug, string $bg = 'transparent'): array
    {
        return [
            'type'        => 'section_row',
            'title'       => $title,
            'layout_mode' => '1-col',
            'bg_style'    => in_array($bg, self::BG_STYLES, true) ? $bg : 'transparent',
            'columns'     => [
                ['span' => 'lg:col-span-12', 'widgets' => [
                    ['type' => 'card_opinion', 'slug' => $slug, 'title' => $title, 'count' => 7],
                ]],
            ],
        ];
    }

    /**
     * A travel/photo section — full-width big destination image grid.
     */
    private static function travelRow(string $title, string $slug, string $bg = 'transparent'): array    {
        return [
            'type'        => 'section_row',
            'title'       => $title,
            'layout_mode' => '1-col',
            'bg_style'    => in_array($bg, self::BG_STYLES, true) ? $bg : 'transparent',
            'columns'     => [
                ['span' => 'lg:col-span-12', 'widgets' => [
                    ['type' => 'card_news_grid', 'slug' => $slug, 'title' => $title, 'count' => 7, 'grid_style' => 'grid_travel'],
                ]],
            ],
        ];
    }

    /**
     * The 3-column hub for one category:
     * big hero + side list + a configurable third column.
     *
     * $third: 'events', 'list' (second same-category list),
     *         or 'ad' (ad banner).
     */
    private static function hubRow(string $title, string $slug, string $bg = 'transparent', string $third = 'list'): array
    {
        if ($third === 'events') {
            $thirdWidget = ['type' => 'card_events_widget', 'count' => 4];
        } elseif ($third === 'ad') {
            $thirdWidget = ['type' => 'card_ad_banner', 'label' => 'Sponsored', 'height' => 90];
        } else {
            $thirdWidget = ['type' => 'card_news_list', 'slug' => $slug, 'title' => 'More Stories', 'count' => 4];
        }

        return [
            'type'        => 'section_row',
            'title'       => $title,
            'layout_mode' => '3-col-543',
            'bg_style'    => in_array($bg, self::BG_STYLES, true) ? $bg : 'transparent',
            'columns'     => [
                ['span' => 'lg:col-span-5', 'widgets' => [['type' => 'card_featured_hero', 'slug' => $slug, 'title' => $title]]],
                ['span' => 'lg:col-span-4', 'widgets' => [['type' => 'card_news_list', 'slug' => $slug, 'title' => 'More Stories', 'count' => 5]]],
                ['span' => 'lg:col-span-3', 'widgets' => [$thirdWidget]],
            ],
        ];
    }

    /**
     * Encode the default layout as a JSON string (unescaped unicode).
     */
    public static function defaultLayoutJson(): string
    {
        return json_encode(self::defaultLayout(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Column spans for a given layout mode.
     */
    public static function modeSpans(string $mode): array
    {
        switch ($mode) {
            case '2-col-75':
                return ['lg:col-span-7', 'lg:col-span-5'];
            case '2-col-66':
                return ['lg:col-span-6', 'lg:col-span-6'];
            case '3-col-543':
                return ['lg:col-span-5', 'lg:col-span-4', 'lg:col-span-3'];
            case '3-col-444':
                return ['lg:col-span-4', 'lg:col-span-4', 'lg:col-span-4'];
            default:
                return ['lg:col-span-12'];
        }
    }

    /**
     * Normalize whatever is stored into the canonical section_row schema.
     */
    public static function normalizeLayout(?array $sections): array
    {
        if (empty($sections)) {
            return [];
        }

        $isRow = true;
        foreach ($sections as $s) {
            if (($s['type'] ?? '') !== 'section_row') {
                $isRow = false;
                break;
            }
        }
        if ($isRow) {
            return self::sanitizeSections($sections);
        }

        $isFlat = true;
        foreach ($sections as $s) {
            if (!in_array($s['type'] ?? '', self::FLAT_TYPES, true)) {
                $isFlat = false;
                break;
            }
        }
        if ($isFlat) {
            return self::flatToRows($sections);
        }

        return self::legacyToRows($sections);
    }

    /**
     * Validate a canonical section_row list.
     */
    public static function sanitizeSections(array $sections): array
    {
        $clean = [];
        foreach ($sections as $row) {
            if (!is_array($row) || ($row['type'] ?? '') !== 'section_row') {
                continue;
            }

            $mode = in_array($row['layout_mode'] ?? '', self::LAYOUT_MODES, true) ? $row['layout_mode'] : '1-col';
            $spans = self::modeSpans($mode);

            $columns = [];
            foreach (($row['columns'] ?? []) as $i => $col) {
                if (!is_array($col) || $i >= count($spans)) {
                    continue;
                }
                $widgets = [];
                foreach (($col['widgets'] ?? []) as $w) {
                    $widget = self::sanitizeWidget(is_array($w) ? $w : []);
                    if ($widget !== null) {
                        $widgets[] = $widget;
                    }
                }
                if (empty($widgets)) {
                    continue;
                }
                $columns[] = [
                    'span'    => is_string($col['span'] ?? null) ? $col['span'] : $spans[$i],
                    'widgets' => $widgets,
                ];
            }

            if (empty($columns)) {
                continue;
            }

            $clean[] = [
                'id'          => $row['id'] ?? ('row_' . uniqid()),
                'type'        => 'section_row',
                'title'       => (string) ($row['title'] ?? ''),
                'layout_mode' => $mode,
                'bg_style'    => in_array($row['bg_style'] ?? '', self::BG_STYLES, true) ? $row['bg_style'] : 'transparent',
                'columns'     => $columns,
            ];
        }

        return $clean;
    }

    private static function sanitizeWidget(array $w): ?array
    {
        $type = $w['type'] ?? '';
        if (!in_array($type, self::WIDGET_TYPES, true)) {
            return null;
        }

        $widget = ['type' => $type];
        $title = trim((string) ($w['title'] ?? ''));
        if ($title !== '') {
            $widget['title'] = $title;
        }

        switch ($type) {
            case 'card_featured_hero':
            case 'card_news_list':
            case 'card_news_grid':
            case 'card_opinion':
                $slug = trim((string) ($w['slug'] ?? ''));
                if ($slug === '') {
                    return null;
                }
                $widget['slug'] = $slug;
                $widget['count'] = max(1, min((int) ($w['count'] ?? 6), 20));
                if ($type === 'card_news_grid') {
                    $widget['grid_style'] = in_array($w['grid_style'] ?? '', self::GRID_STYLES, true) ? $w['grid_style'] : 'grid_standard';
                }
                break;

            case 'featured_stack':
            case 'card_multimedia_video':
            case 'card_events_widget':
                $widget['count'] = max(1, min((int) ($w['count'] ?? 5), 10));
                break;

            case 'card_ad_banner':
                $widget['label'] = trim((string) ($w['label'] ?? '')) ?: 'Sponsored';
                $widget['height'] = max(50, min((int) ($w['height'] ?? 90), 800));
                if (!empty($w['ad_id']) && ctype_digit((string) $w['ad_id'])) {
                    $widget['ad_id'] = (int) $w['ad_id'];
                }
                break;
        }

        return $widget;
    }

    /**
     * Convert the flat single-widget sections (v1) to section_row.
     */
    private static function flatToRows(array $flat): array
    {
        $rows = [];
        foreach ($flat as $s) {
            if (!is_array($s)) {
                continue;
            }
            $type = $s['type'] ?? '';
            $title = trim((string) ($s['title'] ?? ''));
            $bg = self::mapBgFlat($s['bg'] ?? '');

            switch ($type) {
                case 'featured':
                    $rows[] = self::row($title, '1-col', $bg, [
                        ['type' => 'featured_stack', 'count' => max(1, (int) ($s['count'] ?? 3))],
                    ]);
                    break;

                case 'category':
                    $slug = trim((string) ($s['slug'] ?? ''));
                    if ($slug === '') {
                        continue 2;
                    }
                    $count = max(1, (int) ($s['count'] ?? 6));
                    $style = in_array($s['style'] ?? '', self::FLAT_STYLES, true) ? $s['style'] : 'grid';

                    if ($style === 'hub' || $style === 'hero_list') {
                        $three = $style === 'hub';
                        $mode = $three ? '3-col-543' : '2-col-75';
                        $spans = self::modeSpans($mode);
                        $cols = [
                            ['type' => 'card_featured_hero', 'slug' => $slug, 'title' => $title],
                            ['type' => 'card_news_list', 'slug' => $slug, 'title' => 'More Stories', 'count' => max(1, $count - 1)],
                        ];
                        if ($three) {
                            $cols[] = ['type' => 'card_events_widget', 'count' => 4];
                        }
                        $rows[] = self::row($title, $mode, $bg, $cols);
                    } else {
                        $gs = $style === 'big_grid' ? 'grid_hero_lead' : ($style === 'compact' ? 'grid_compact_list' : 'grid_standard');
                        $rows[] = self::row($title, '1-col', $bg, [
                            ['type' => 'card_news_grid', 'slug' => $slug, 'title' => $title, 'count' => $count, 'grid_style' => $gs],
                        ]);
                    }
                    break;

                case 'video':
                    $rows[] = self::row($title !== '' ? $title : 'Eyewear Videos', '1-col', $bg, [
                        ['type' => 'card_multimedia_video', 'title' => $title, 'count' => max(1, (int) ($s['count'] ?? 5))],
                    ]);
                    break;

                case 'events':
                    $rows[] = self::row('', '1-col', $bg, [
                        ['type' => 'card_events_widget', 'count' => max(1, (int) ($s['count'] ?? 5))],
                    ]);
                    break;

                case 'ad':
                    $rows[] = self::row('', '1-col', $bg, [
                        ['type' => 'card_ad_banner', 'label' => $s['label'] ?? 'Sponsored', 'height' => (int) ($s['height'] ?? 90)],
                    ]);
                    break;
            }
        }

        return self::sanitizeSections($rows);
    }

    /**
     * Convert legacy flat native section objects (no section_row wrapper).
     */
    private static function legacyToRows(array $sections): array
    {
        $rows = [];
        foreach ($sections as $s) {
            if (!is_array($s)) {
                continue;
            }
            $type = $s['type'] ?? '';
            $title = trim((string) ($s['title'] ?? ''));
            $bg = self::mapBgFlat($s['bg_style'] ?? '');

            switch ($type) {
                case 'featured_stack':
                case 'bises_stack':
                case 'featured':
                case 'featured_spotlight':
                case 'featured_carousel':
                case 'featured_ranked':
                case 'hero_split':
                case 'opinion':
                    $rows[] = self::row($title, '1-col', $bg, [
                        ['type' => 'featured_stack', 'count' => max(1, (int) ($s['count'] ?? 3))],
                    ]);
                    break;

                case 'nz_hub':
                    $slug = $s['slug'] ?? 'specs-lifestyle';
                    $rows[] = self::row($title !== '' ? $title : 'Glasses Lifestyle', '3-col-543', 'accent-gradient', [
                        ['type' => 'card_featured_hero', 'slug' => $slug, 'title' => $title],
                        ['type' => 'card_news_list', 'slug' => $s['sidebar_slug'] ?? $slug, 'title' => $s['sidebar_title'] ?? 'More Stories', 'count' => 5],
                        ['type' => 'card_events_widget', 'count' => 4],
                    ]);
                    break;

                case 'diaspora_scroll':
                case 'category_default':
                case 'category_economy':
                case 'category_sports':
                case 'category_horizontal':
                case 'category_grid_small':
                    $slug = $s['slug'] ?? '';
                    if ($slug === '') {
                        continue 2;
                    }
                    $rows[] = self::row($title, '2-col-75', $bg, [
                        ['type' => 'card_news_grid', 'slug' => $slug, 'title' => $title, 'count' => max(1, (int) ($s['count'] ?? 6))],
                        ['type' => 'card_news_list', 'slug' => $slug, 'title' => 'More Stories', 'count' => 5],
                    ]);
                    break;

                case 'multimedia':
                case 'card_multimedia_video':
                    $rows[] = self::row($title !== '' ? $title : 'Eyewear Videos', '1-col', $bg, [
                        ['type' => 'card_multimedia_video', 'title' => $title, 'count' => max(1, (int) ($s['count'] ?? 5))],
                    ]);
                    break;

                case 'card_events_widget':
                    $rows[] = self::row('', '1-col', $bg, [
                        ['type' => 'card_events_widget', 'count' => max(1, (int) ($s['count'] ?? 5))],
                    ]);
                    break;

                case 'ad':
                case 'card_ad_banner':
                    $rows[] = self::row('', '1-col', $bg, [
                        ['type' => 'card_ad_banner', 'label' => $s['label'] ?? 'Sponsored', 'height' => (int) ($s['height'] ?? 90)],
                    ]);
                    break;

                default:
                    break;
            }
        }

        return self::sanitizeSections($rows);
    }

    /**
     * Build a canonical section_row from a title/mode/bg and a list of widgets
     * (one per column, spans derived from the layout mode).
     */
    private static function row(string $title, string $mode, string $bg, array $widgets): array
    {
        $spans = self::modeSpans($mode);
        $columns = [];
        foreach ($widgets as $i => $w) {
            if ($i >= count($spans)) {
                break;
            }
            $columns[] = [
                'span'    => $spans[$i],
                'widgets' => [$w],
            ];
        }
        return [
            'type'        => 'section_row',
            'title'       => $title,
            'layout_mode' => $mode,
            'bg_style'    => $bg,
            'columns'     => $columns,
        ];
    }

    private static function mapBgFlat(string $bg): string
    {
        switch ($bg) {
            case 'accent':
                return 'accent-gradient';
            case 'card':
                return 'surface-card';
            default:
                return 'transparent';
        }
    }
}
