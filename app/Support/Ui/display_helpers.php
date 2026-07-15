<?php

if (! function_exists('display_unknown')) {
    /**
     * Safe empty-state placeholder for ops-console display (ASCII default).
     */
    function display_unknown(?string $value = null, string $fallback = '--'): string
    {
        if ($value === null) {
            return $fallback;
        }

        $text = clean_display_text($value, '');

        return $text === '' ? $fallback : $text;
    }
}

if (! function_exists('display_sep_dot')) {
    function display_sep_dot(): string
    {
        return ' · ';
    }
}

if (! function_exists('display_sep_dash')) {
    function display_sep_dash(): string
    {
        return ' - ';
    }
}

if (! function_exists('clean_display_text')) {
    /**
     * Strip replacement chars and tags; never mutates stored domain data.
     */
    function clean_display_text(mixed $value, string $fallback = '--'): string
    {
        if ($value === null) {
            return $fallback;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            $text = (string) $value;
        } elseif (is_string($value)) {
            $text = $value;
        } else {
            return $fallback;
        }

        $text = str_replace("\u{FFFD}", '', $text);
        $text = strip_tags($text);
        $text = str_replace(["\0", "\r"], '', $text);
        $text = trim($text);

        return $text === '' ? $fallback : $text;
    }
}
