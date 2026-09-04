<?php

declare(strict_types=1);

namespace Songwunsch;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Reduce HTML from the page editor to what the footer pages may contain.
 *
 * Admins write these pages, and admins are trusted with everything else --
 * but the pages are shown to every visitor, so the stored markup is limited
 * to text structure: headings, paragraphs, lists, links, tables, quotes,
 * emphasis. Everything else is dropped: unknown elements lose their tags and
 * keep their text, scripts, styles, frames and forms go with their content,
 * attributes are reduced to the few listed, and a link may only point to a
 * web address, a mail address, a phone number or a page of this site. The
 * result is what the browser gets, unescaped, in a <div class="prose">.
 */
final class Html
{
    /** Elements that stay, with the attributes they may carry. */
    private const ALLOWED = [
        'p'          => [],
        'br'         => [],
        'h2'         => [],
        'h3'         => [],
        'h4'         => [],
        'strong'     => [],
        'b'          => [],
        'em'         => [],
        'i'          => [],
        'u'          => [],
        's'          => [],
        'sub'        => [],
        'sup'        => [],
        'code'       => [],
        'pre'        => [],
        'hr'         => [],
        'blockquote' => [],
        'ul'         => [],
        'ol'         => ['start'],
        'li'         => [],
        'a'          => ['href', 'target'],
        'table'      => [],
        'thead'      => [],
        'tbody'      => [],
        'tfoot'      => [],
        'tr'         => [],
        'th'         => ['colspan', 'rowspan'],
        'td'         => ['colspan', 'rowspan'],
        'figure'     => [],
        'figcaption' => [],
    ];

    /** Elements whose content goes with them -- nothing inside is text meant for the reader. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'noscript', 'template', 'svg', 'math', 'head', 'title', 'meta', 'link', 'base'];

    /** Elements renamed rather than unwrapped: a page has one h1, its title. */
    private const RENAME = ['h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4'];

    /** Clean a fragment of HTML; '' for nothing worth keeping. */
    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        // The XML prologue tells libxml the encoding; the wrapper keeps the
        // fragment together and spares us html/body. No network access for
        // entities, no implied elements, no doctype.
        $ok = @$doc->loadHTML(
            '<?xml encoding="utf-8"?><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        if (!$ok || $doc->documentElement === null) {
            return '';
        }

        // libxml may leave the processing instruction in front of the wrapper.
        $root = null;
        foreach ($doc->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $root = $node;
                break;
            }
        }
        if ($root === null) {
            return '';
        }

        self::cleanChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    /** Walk the children of a node; the list is copied since it changes underneath. */
    private static function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            self::cleanNode($node);
        }
    }

    private static function cleanNode(DOMNode $node): void
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }

        if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            $parent->removeChild($node);

            return;
        }
        if (!($node instanceof DOMElement)) {
            // Text stays as it is; serialising escapes it again.
            return;
        }

        $name = strtolower($node->tagName);

        if (in_array($name, self::DROP, true)) {
            $parent->removeChild($node);

            return;
        }

        if (isset(self::RENAME[$name])) {
            $node = self::rename($node, self::RENAME[$name]);
            $name = self::RENAME[$name];
        }

        if (!isset(self::ALLOWED[$name])) {
            // Unknown element: its children take its place.
            self::cleanChildren($node);
            while ($node->firstChild !== null) {
                $parent->insertBefore($node->firstChild, $node);
            }
            $parent->removeChild($node);

            return;
        }

        // Attributes: only the listed ones, each checked.
        foreach (iterator_to_array($node->attributes) as $attr) {
            $attrName = strtolower($attr->name);
            if (!in_array($attrName, self::ALLOWED[$name], true) || !self::attributeOk($name, $attrName, $attr->value)) {
                $node->removeAttribute($attr->name);
            }
        }
        if ($name === 'a') {
            if (!$node->hasAttribute('href')) {
                // A link without a target is plain text.
                self::cleanChildren($node);
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                return;
            }
            // A new window must not get a handle on this one.
            if ($node->getAttribute('target') === '_blank') {
                $node->setAttribute('rel', 'noopener');
            } else {
                $node->removeAttribute('target');
            }
        }

        self::cleanChildren($node);
    }

    /** Is this value acceptable for the attribute? */
    private static function attributeOk(string $element, string $attr, string $value): bool
    {
        $value = trim($value);

        return match ($attr) {
            'href'    => self::hrefOk($value),
            'target'  => $value === '_blank',
            'start', 'colspan', 'rowspan' => preg_match('/^[0-9]{1,4}$/', $value) === 1,
            default   => false,
        };
    }

    /**
     * Web, mail and phone addresses, anchors and the site's own paths.
     * Anything with another scheme -- javascript:, data:, vbscript: -- is
     * refused, as is a protocol-relative //host.
     */
    private static function hrefOk(string $href): bool
    {
        if ($href === '' || preg_match('/[\x00-\x1f\x7f]/', $href) === 1) {
            return false;
        }
        if (preg_match('#^(https?://|mailto:|tel:)#i', $href) === 1) {
            return true;
        }
        if ($href[0] === '#') {
            return true;
        }
        if ($href[0] === '/' || $href[0] === '\\') {
            // The site's own path -- but not //host or /\host, which browsers
            // read as another site.
            return !str_starts_with($href, '//') && !str_starts_with($href, '/\\') && $href[0] !== '\\';
        }
        // A bare relative path such as "page/faq" -- but nothing that looks
        // like a scheme.
        return preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) !== 1;
    }

    /** Replace an element by one with another name, moving children along. */
    private static function rename(DOMElement $node, string $newName): DOMElement
    {
        $doc = $node->ownerDocument;
        $new = $doc->createElement($newName);
        while ($node->firstChild !== null) {
            $new->appendChild($node->firstChild);
        }
        $node->parentNode?->replaceChild($new, $node);

        return $new;
    }
}
