<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\TestCase;

/**
 * Static guard against the app's most common XSS: a Blade `{{ }}` echo placed
 * inside a JavaScript string.
 *
 *     x-data="{ tab: '{{ $tab }}' }"            <- vulnerable
 *
 *     @click="copy('{{ $promo->code }}')"       <- vulnerable
 *     x-data="{ tab: @js($tab) }"               <- safe
 *
 * `{{ }}` HTML-encodes a quote to `&#039;`, but the browser decodes entities
 * in an attribute value before Alpine (or an inline handler) evaluates it, so
 * the quote comes back and breaks out of the string: a value of
 * `'-alert(1)-'` runs. `@js()` / `Js::from()` emit a JSON literal instead and
 * are safe in both attributes and <script> blocks.
 *
 * This scans every Blade view, so a new instance fails the build no matter
 * which page it's on. It needs no database or app boot.
 */
class BladeJsContextTest extends TestCase
{
    /** Attributes whose value is evaluated as JavaScript. */
    private const JS_ATTRIBUTE = '/\s(?:x-[a-z:.\-]+|@[a-z:.\-]+|:[a-z\-]+|on[a-z]+)="([^"]*)"/s';

    /**
     * True when a Blade echo in a JS attribute value sits inside a JavaScript
     * string literal, whether it's the whole string ('{{ $x }}') or part of
     * one (confirm('Delete {{ $name }}?')). A raw {!! !!} always counts.
     *
     * Walks the value tracking whether we're inside '...' or `...`. Blade
     * echoes and @js()/@json() calls are skipped as opaque, because their own
     * PHP quotes aren't JavaScript quotes. An echo outside any string
     * (selectCombo({{ $id }}, ...)) is left alone: it's a number or JSON.
     */
    private function echoInsideJsString(string $value): bool
    {
        if (str_contains($value, '{!!')) {
            return true;
        }

        $quote = null;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if (substr($value, $i, 4) === '{{--') {
                $end = strpos($value, '--}}', $i);
                $i = $end === false ? $length : $end + 3;
            } elseif (substr($value, $i, 2) === '{{') {
                if ($quote !== null) {
                    return true;
                }
                $end = strpos($value, '}}', $i);
                $i = $end === false ? $length : $end + 1;
            } elseif ($quote === null && preg_match('/\G@(?:js|json)\(/', $value, $m, 0, $i)) {
                // Skip the directive's balanced parentheses.
                $depth = 0;
                for ($i += strlen($m[0]) - 1; $i < $length; $i++) {
                    $depth += $value[$i] === '(' ? 1 : ($value[$i] === ')' ? -1 : 0);
                    if ($depth === 0) {
                        break;
                    }
                }
            } elseif ($char === '\\') {
                $i++; // escaped character inside a JS string
            } elseif ($char === "'" || $char === '`') {
                $quote = $quote === null ? $char : ($quote === $char ? null : $quote);
            }
        }

        return false;
    }

    /** '{{ ... }}' / "{{ ... }}" / `{{ ... }}` inside a <script> block. */
    private const ECHO_IN_SCRIPT_STRING = '/([\'"`])\{\{\s*((?:(?!\}\}).)*?)\s*\}\}\1/s';

    /**
     * Expressions that can only produce server-generated, quote-free text, so
     * a quoted echo of them inside <script> is fine. Anything else must use
     *
     * @js(). Keep this list short and literal.
     */
    private const SAFE_SCRIPT_EXPRESSIONS = [
        '/^route\(/',            // URLs built by the router
        '/^url\(/',
        '/^asset\(/',
        '/^csrf_token\(\)$/',
        '/^config\(\'app\.(name|url)\'\)$/',
    ];

    private function views(): iterable
    {
        $root = dirname(__DIR__, 3).'/resources/views';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                yield str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)) => file_get_contents($file->getPathname());
            }
        }
    }

    private function lineOf(string $source, int $offset): int
    {
        return substr_count($source, "\n", 0, $offset) + 1;
    }

    public function test_no_blade_echo_sits_inside_a_javascript_string_in_an_attribute(): void
    {
        $hits = [];

        foreach ($this->views() as $path => $source) {
            if (! preg_match_all(self::JS_ATTRIBUTE, $source, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[1] as [$value, $offset]) {
                if ($this->echoInsideJsString($value)) {
                    $hits[] = "{$path}:{$this->lineOf($source, $offset)}";
                }
            }
        }

        $this->assertSame([], $hits,
            "Blade {{ }} inside a JavaScript string in an Alpine/inline-handler attribute (XSS: a quote in the value breaks out of the string).\n"
            ."Use @js(\$value) instead of '{{ \$value }}'.\n  ".implode("\n  ", $hits));
    }

    public function test_script_blocks_only_quote_echo_router_generated_values(): void
    {
        $hits = [];

        foreach ($this->views() as $path => $source) {
            if (! preg_match_all('/<script\b[^>]*>(.*?)<\/script>/s', $source, $blocks, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($blocks[1] as [$body, $blockOffset]) {
                if (! preg_match_all(self::ECHO_IN_SCRIPT_STRING, $body, $echoes, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                foreach ($echoes[2] as [$expression, $offset]) {
                    $expression = trim($expression);
                    $safe = false;
                    foreach (self::SAFE_SCRIPT_EXPRESSIONS as $pattern) {
                        $safe = $safe || preg_match($pattern, $expression);
                    }
                    if (! $safe) {
                        $hits[] = "{$path}:{$this->lineOf($source, $blockOffset + $offset)}  {{ ".substr(preg_replace('/\s+/', ' ', $expression), 0, 60).' }}';
                    }
                }
            }
        }

        $this->assertSame([], $hits,
            "Quoted Blade {{ }} inside a <script> block. Unless it's a router-generated URL, use @js(\$value) (no surrounding quotes).\n  "
            .implode("\n  ", $hits));
    }
}
