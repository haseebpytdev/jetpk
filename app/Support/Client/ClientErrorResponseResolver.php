<?php

namespace App\Support\Client;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Selects and returns a single full-document error view (themed or generic),
 * or delegates browser document errors to the Next.js public surface.
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
    public function response(string $code, array $data = [], ?int $status = null): Response|RedirectResponse
    {
        if ($this->shouldDelegateBrowserErrorToNext($code)) {
            $path = match ($code) {
                '404' => '/access-denied?reason=not-found',
                '403' => '/access-denied?reason=forbidden',
                '419' => '/login?reason=session-expired',
                '429' => '/access-denied?reason=rate-limited',
                '503' => '/access-denied?reason=unavailable',
                default => '/access-denied?reason=service-error',
            };

            return redirect()->to($path);
        }

        $statusCode = $status ?? (int) $code;
        $view = $this->resolveView($code);

        if (! View::exists($view)) {
            $view = 'errors.'.$code;
        }

        return response()->view($view, $data, $statusCode);
    }

    public function fromHttpException(HttpExceptionInterface $exception, array $data = []): Response|RedirectResponse
    {
        $status = $exception->getStatusCode();
        $code = (string) $status;

        if ($status === 403 && $exception->getMessage() !== '' && ! app()->environment('production')) {
            $data['message'] = $exception->getMessage();
        }

        return $this->response($code, $data, $status);
    }

    private function shouldDelegateBrowserErrorToNext(string $code): bool
    {
        if (! in_array($code, self::SUPPORTED_CODES, true)) {
            return false;
        }

        if (! app()->environment('production')) {
            return false;
        }

        if (! filter_var(env('OTA_PUBLIC_ERRORS_DELEGATE_TO_NEXT', true), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        $request = request();
        if ($request->expectsJson() || $request->ajax()) {
            return false;
        }

        if ($request->is('api/*', 'api/dashboard', 'api/dashboard/*', 'livewire/*')) {
            return false;
        }

        return true;
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
