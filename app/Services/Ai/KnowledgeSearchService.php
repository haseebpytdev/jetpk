<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\File;

/**
 * Search approved static FAQ markdown under ai-assistant/knowledge only.
 * Never reads .env, source PHP, or secrets.
 */
final class KnowledgeSearchService
{
    /**
     * @return list<array{slug: string, title: string, excerpt: string, score: float}>
     */
    public function search(string $query, int $limit = 3): array
    {
        if (! (bool) config('ota.ai_assistant.knowledge_enabled', true)) {
            return [];
        }

        $dir = base_path('ai-assistant/knowledge');
        if (! File::isDirectory($dir)) {
            return [];
        }

        $tokens = $this->tokens($query);
        if ($tokens === []) {
            return [];
        }

        $hits = [];
        foreach (File::files($dir) as $file) {
            if (strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            $slug = $file->getFilenameWithoutExtension();
            // Refuse path escape / non-knowledge files.
            if (preg_match('/^[a-z0-9\-_]+$/i', $slug) !== 1) {
                continue;
            }
            $body = (string) File::get($file->getPathname());
            $title = $this->titleFromMarkdown($body, $slug);
            $score = $this->score($body.' '.$title, $tokens);
            if ($score <= 0) {
                continue;
            }
            $hits[] = [
                'slug' => $slug,
                'title' => $title,
                'excerpt' => $this->excerpt($body, 320),
                'score' => $score,
            ];
        }

        usort($hits, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($hits, 0, max(1, min(5, $limit)));
    }

    /**
     * @return list<string>
     */
    private function tokens(string $query): array
    {
        $q = mb_strtolower(strip_tags($query));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $q) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if (mb_strlen($p) < 3) {
                continue;
            }
            $out[$p] = $p;
        }

        return array_values($out);
    }

    /**
     * @param  list<string>  $tokens
     */
    private function score(string $haystack, array $tokens): float
    {
        $h = mb_strtolower($haystack);
        $score = 0.0;
        foreach ($tokens as $t) {
            if (str_contains($h, $t)) {
                $score += 1.0;
            }
        }

        return $score;
    }

    private function titleFromMarkdown(string $body, string $fallback): string
    {
        if (preg_match('/^#\s+(.+)$/m', $body, $m) === 1) {
            return trim($m[1]);
        }

        return ucwords(str_replace(['-', '_'], ' ', $fallback));
    }

    private function excerpt(string $body, int $max): string
    {
        $plain = trim(preg_replace('/^#+\s*.+$/m', '', $body) ?? $body);
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        if (mb_strlen($plain) <= $max) {
            return $plain;
        }

        return rtrim(mb_substr($plain, 0, $max - 1)).'…';
    }
}
