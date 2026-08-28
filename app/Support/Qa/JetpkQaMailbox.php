<?php

namespace App\Support\Qa;

use Illuminate\Support\Facades\File;
use Symfony\Component\Mime\Email;


/**
 * Ephemeral QA mailbox (SQLite outside primary DB). Phase-only harness.
 *
 * Activate only when JETPK_QA_MAIL_SINK_ENABLED=true and recipient equals
 * the exact value of JETPK_QA_MAIL_SINK_RECIPIENT (no wildcards).
 */
final class JetpkQaMailbox
{
    public static function isEnabled(): bool
    {
        return (bool) config('jetpk_qa_mail.enabled', false);
    }

    public static function exactRecipient(): ?string
    {
        $recipient = strtolower(trim((string) config('jetpk_qa_mail.recipient', '')));
        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        // Refuse wildcard / catch-all style domains for safety.
        if (str_contains($recipient, '*') || str_starts_with($recipient, '@')) {
            return null;
        }

        return $recipient;
    }

    /**
     * @param  list<string>  $recipients
     */
    public static function shouldCapture(array $recipients): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        $exact = self::exactRecipient();
        if ($exact === null) {
            return false;
        }

        $normalized = array_values(array_unique(array_map(
            static fn (string $email): string => strtolower(trim($email)),
            $recipients,
        )));

        return count($normalized) === 1 && $normalized[0] === $exact;
    }

    public static function sqlitePath(): string
    {
        return storage_path('app/qa/jp-final-mailbox.sqlite');
    }

    public static function ensureSchema(): void
    {
        $path = self::sqlitePath();
        File::ensureDirectoryExists(dirname($path));
        if (! File::exists($path)) {
            File::put($path, '');
        }

        $pdo = self::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS qa_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                recipient TEXT NOT NULL,
                subject TEXT,
                html_body TEXT,
                text_body TEXT,
                verification_url TEXT,
                created_at TEXT NOT NULL,
                expires_at TEXT NOT NULL
            )'
        );
    }

    public static function captureEmail(Email $email): void
    {
        self::ensureSchema();

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();
        $htmlStr = is_string($html) ? $html : null;
        $textStr = is_string($text) ? $text : null;
        $combined = ($htmlStr ?? '')."\n".($textStr ?? '');
        $verificationUrl = self::extractVerificationUrl($combined);

        $to = [];
        foreach ($email->getTo() as $addr) {
            $to[] = strtolower(trim($addr->getAddress()));
        }

        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO qa_messages (recipient, subject, html_body, text_body, verification_url, created_at, expires_at)
             VALUES (:recipient, :subject, :html_body, :text_body, :verification_url, :created_at, :expires_at)'
        );
        $now = now();
        $stmt->execute([
            ':recipient' => $to[0] ?? (self::exactRecipient() ?? ''),
            ':subject' => (string) $email->getSubject(),
            ':html_body' => $htmlStr,
            ':text_body' => $textStr,
            ':verification_url' => $verificationUrl,
            ':created_at' => $now->toIso8601String(),
            ':expires_at' => $now->copy()->addHours(24)->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function latest(?string $recipient = null): ?array
    {
        self::ensureSchema();
        $pdo = self::pdo();
        if ($recipient !== null) {
            $stmt = $pdo->prepare('SELECT * FROM qa_messages WHERE recipient = :recipient ORDER BY id DESC LIMIT 1');
            $stmt->execute([':recipient' => strtolower(trim($recipient))]);
        } else {
            $stmt = $pdo->query('SELECT * FROM qa_messages ORDER BY id DESC LIMIT 1');
        }

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function destroyStorage(): void
    {
        $path = self::sqlitePath();
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    private static function extractVerificationUrl(string $body): ?string
    {
        if (preg_match('#https?://[^\s\"\'<>]+/verify-email/[^\s\"\'<>]+#i', $body, $m) !== 1) {
            return null;
        }

        return html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5);
    }

    private static function pdo(): \PDO
    {
        $pdo = new \PDO('sqlite:'.self::sqlitePath());
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
