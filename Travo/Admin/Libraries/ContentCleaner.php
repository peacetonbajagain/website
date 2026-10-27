<?php
namespace Travo\Admin\Libraries;

/** Whitelist HTML sanitiser used on every save of post/page content. */
class ContentCleaner
{
    private const TAGS = ['p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote',
        'a', 'img', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'pre', 'code', 'span', 'div'];
    private const ATTRS = ['a' => ['href', 'title'], 'img' => ['src', 'alt', 'width', 'height']];
    private const DROP  = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'math'];

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $dom->getElementById('root');
        if (! $root) {
            return esc($html);
        }
        self::walk($root);
        $out = '';
        foreach ($root->childNodes as $n) {
            $out .= $dom->saveHTML($n);
        }
        return $out;
    }

    private static function walk(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }
            if (! in_array($tag, self::TAGS, true)) {
                self::walk($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (! in_array($name, self::ATTRS[$tag] ?? [], true)) {
                    $child->removeAttribute($attr->name);
                } elseif (in_array($name, ['href', 'src'], true) && ! preg_match('#^(https?://|/|mailto:|\#)#i', trim($attr->value))) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a' && $child->hasAttribute('href')) {
                $child->setAttribute('rel', 'noopener noreferrer nofollow');
            }
            self::walk($child);
        }
    }
}
