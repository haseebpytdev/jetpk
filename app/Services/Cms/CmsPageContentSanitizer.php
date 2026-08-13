<?php

namespace App\Services\Cms;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist sanitizer for CmsPage builder HTML (not About Us overrides).
 */
final class CmsPageContentSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'a', 'article', 'aside', 'blockquote', 'br', 'cite', 'div', 'em', 'figcaption',
        'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'i',
        'img', 'li', 'mark', 'nav', 'ol', 'p', 'section', 'small', 'span', 'strong',
        'sub', 'sup', 'table', 'tbody', 'td', 'th', 'thead', 'time', 'tr', 'u', 'ul',
        'b', 'dl', 'dt', 'dd',
    ];

    /** @var list<string> */
    private const GLOBAL_ATTRS = [
        'class', 'id', 'title', 'lang',
        'data-jp-block', 'data-jp-hidden', 'data-layout', 'data-variant', 'data-jp-video',
    ];

    /** @var array<string, list<string>> */
    private const TAG_ATTRS = [
        'a' => ['href', 'rel', 'target'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
        'time' => ['datetime'],
    ];

    public function sanitizeForStorage(mixed $raw): string
    {
        return $this->sanitize((string) $raw, stripHidden: false);
    }

    public function formatForDisplay(?string $stored, bool $stripHidden = true): string
    {
        return $this->sanitize((string) $stored, stripHidden: $stripHidden);
    }

    public function formatForPublicDisplay(?string $stored): string
    {
        return $this->formatForDisplay($stored, stripHidden: true);
    }

    public function formatForPreviewDisplay(?string $stored): string
    {
        return $this->formatForDisplay($stored, stripHidden: true);
    }

    private function sanitize(string $html, bool $stripHidden): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $html) ?? $html;

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="jp-cms-root">'.$html.'</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('jp-cms-root');
        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->scrubNode($root, $stripHidden);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private function scrubNode(DOMNode $node, bool $stripHidden): void
    {
        if (! $node->hasChildNodes()) {
            return;
        }

        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if ($tag === 'script' || $tag === 'style' || $tag === 'iframe' || $tag === 'object' || $tag === 'embed' || $tag === 'form') {
                    $child->parentNode?->removeChild($child);

                    continue;
                }

                if ($stripHidden && $this->isHiddenBlock($child)) {
                    $child->parentNode?->removeChild($child);

                    continue;
                }

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    $this->unwrap($child, $stripHidden);

                    continue;
                }

                $this->filterAttributes($child);
                $this->scrubNode($child, $stripHidden);

                continue;
            }

            if ($child->nodeType === XML_COMMENT_NODE) {
                $child->parentNode?->removeChild($child);
            }
        }
    }

    private function isHiddenBlock(DOMElement $el): bool
    {
        $hidden = strtolower(trim($el->getAttribute('data-jp-hidden')));

        return $hidden === 'true' || $hidden === '1';
    }

    private function unwrap(DOMElement $el, bool $stripHidden): void
    {
        $this->scrubNode($el, $stripHidden);
        $parent = $el->parentNode;
        if ($parent === null) {
            return;
        }
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }

    private function filterAttributes(DOMElement $el): void
    {
        $tag = strtolower($el->tagName);
        $allowed = array_merge(self::GLOBAL_ATTRS, self::TAG_ATTRS[$tag] ?? []);
        $remove = [];
        foreach ($el->attributes ?? [] as $attr) {
            $name = strtolower($attr->name);
            if (str_starts_with($name, 'on')) {
                $remove[] = $attr->name;

                continue;
            }
            if (! in_array($name, $allowed, true)) {
                $remove[] = $attr->name;

                continue;
            }
            if (in_array($name, ['href', 'src'], true) && ! $this->isSafeUrl((string) $attr->value, $name === 'src')) {
                $remove[] = $attr->name;
            }
        }
        foreach ($remove as $name) {
            $el->removeAttribute($name);
        }
    }

    private function isSafeUrl(string $url, bool $media): bool
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#')) {
            return true;
        }
        $lower = strtolower($url);
        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:') || str_starts_with($lower, 'vbscript:')) {
            return false;
        }
        if (str_starts_with($url, '/') || str_starts_with($url, './') || str_starts_with($lower, 'mailto:')) {
            return true;
        }
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return in_array($scheme, ['http', 'https'], true);
    }
}
