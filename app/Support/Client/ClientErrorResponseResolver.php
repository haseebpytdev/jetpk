<?php

namespace App\Support\Client;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Selects and returns a single full-document error view (themed or generic).
 */
final class ClientErrorResponseResolver
{
    /** @var list<string> */
    public const SUPPORTED_CODES = ['403', '404', '419', '429', '500', '503'];

    public static function supportsStatus(int $status): bool
    {
        return in_array((string) $status, self::SUPPORTED_CODES, true);
    }

    public function resolveView(string $code): string
    {
        if (! in_array($code, self::SUPPORTED_CODES, true)) {
            return 'errors.'.$code;
        }

        return client_error_view($code);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function response(string $code, array $data = [], ?int $status = null): Response
    {
        $statusCode = $status ?? (int) $code;
        $view = $this->resolveView($code);

        if (! View::exists($view)) {
            $view = 'errors.'.$code;
        }

        return response()->view($view, $data, $statusCode);
    }

    public function fromHttpException(HttpExceptionInterface $exception, array $data = []): Response
    {
        $status = $exception->getStatusCode();
        $code = (string) $status;

        if ($status === 403 && $exception->getMessage() !== '' && ! app()->environment('production')) {
            $data['message'] = $exception->getMessage();
        }

        return $this->response($code, $data, $status);
    }

    /**
     * @return array{doctype: int, html: int, head: int, body: int, header: int, main: int, footer: int, panel: int, generic_card: int}
     */
    public static function countDocumentMarkers(string $html): array
    {
        return [
            'doctype' => preg_match_all('/<!doctype/i', $html) ?: 0,
            'html' => preg_match_all('/<html(\s|>|\/)/i', $html) ?: 0,
            'head' => preg_match_all('/<head(\s|>|\/)/i', $html) ?: 0,
            'body' => preg_match_all('/<body(\s|>|\/)/i', $html) ?: 0,
            'header' => preg_match_all('/<header(\s|>|\/)/i', $html) ?: 0,
            'main' => preg_match_all('/<main(\s|>|\/)/i', $html) ?: 0,
            'footer' => preg_match_all('/<footer(\s|>|\/)/i', $html) ?: 0,
            'panel' => preg_match_all('/class="jp-error-panel"/', $html) ?: 0,
            'generic_card' => preg_match_all('/class="card"/', $html) ?: 0,
        ];
    }

    /**
     * @return list<string>
     */
    public static function themedDocumentIssues(string $html): array
    {
        $counts = self::countDocumentMarkers($html);
        $issues = [];

        foreach (['doctype', 'html', 'body'] as $key) {
            if ($counts[$key] !== 1) {
                $issues[] = $key.'='.$counts[$key].' (expected 1)';
            }
        }

        foreach (['head', 'header', 'main', 'footer', 'panel'] as $key) {
            if ($counts[$key] !== 1) {
                $issues[] = $key.'='.$counts[$key].' (expected 1)';
            }
        }

        if ($counts['generic_card'] > 0) {
            $issues[] = 'generic_card='.$counts['generic_card'].' (expected 0 for themed shell)';
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    public static function genericDocumentIssues(string $html): array
    {
        $counts = self::countDocumentMarkers($html);
        $issues = [];

        foreach (['doctype', 'html', 'body'] as $key) {
            if ($counts[$key] !== 1) {
                $issues[] = $key.'='.$counts[$key].' (expected 1)';
            }
        }

        if ($counts['head'] !== 1) {
            $issues[] = 'head='.$counts['head'].' (expected 1)';
        }

        if ($counts['panel'] > 0) {
            $issues[] = 'panel='.$counts['panel'].' (expected 0 for generic fallback)';
        }

        if ($counts['generic_card'] !== 1) {
            $issues[] = 'generic_card='.$counts['generic_card'].' (expected 1)';
        }

        return $issues;
    }
}
