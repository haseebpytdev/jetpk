<?php

namespace App\Support\Audits;

final class JetpkSvgSafetyAuditor
{
    /**
     * @return array{pass: bool, issues: list<string>, path: string}
     */
    public function auditFile(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            return ['pass' => false, 'issues' => ['missing_file'], 'path' => $absolutePath];
        }

        return $this->auditContent((string) file_get_contents($absolutePath), $absolutePath);
    }

    /**
     * @return array{pass: bool, issues: list<string>, path: string}
     */
    public function auditContent(string $content, string $path = ''): array
    {
        $issues = [];
        $content = strtolower($content);
        $checks = [
            'script_tag' => '<script',
            'foreign_object' => 'foreignobject',
            'javascript_url' => 'javascript:',
            'on_event_handler' => ' on[a-z]+=',
            'external_href' => 'xlink:href="http',
            'remote_image' => '<image[^>]+href="http',
        ];

        foreach ($checks as $label => $pattern) {
            if (preg_match('/'.$pattern.'/i', $content) === 1) {
                $issues[] = $label;
            }
        }

        return [
            'pass' => $issues === [],
            'issues' => $issues,
            'path' => $path,
        ];
    }
}
