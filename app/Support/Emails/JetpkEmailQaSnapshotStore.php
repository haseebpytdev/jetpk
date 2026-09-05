<?php

namespace App\Support\Emails;

use Illuminate\Support\Facades\File;
use PDO;

/**
 * Temporary SQLite evidence store outside the business database.
 */
final class JetpkEmailQaSnapshotStore
{
    public function __construct(
        protected string $path,
    ) {
        $this->ensureSchema();
    }

    public static function createForRun(string $runId): self
    {
        $dir = storage_path('app/email-qa/live');
        File::ensureDirectoryExists($dir, 0700);
        $path = $dir.DIRECTORY_SEPARATOR.$runId.'.sqlite';

        return new self($path);
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function insert(array $row): void
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO snapshots (
                run_id, scenario_id, correlation_id, scenario_name, intended_recipient_role,
                proof_class, trigger_source, mailable_class, subject, from_identity, reply_to,
                test_recipient, branding_json, logo_url, support_json, html_body, plain_text_body,
                runtime_sha, public_build_id, render_timestamp, send_timestamp, transport_result,
                content_audit_json, visual_audit_json, screenshot_paths, raw_artifact_sha256
            ) VALUES (
                :run_id, :scenario_id, :correlation_id, :scenario_name, :intended_recipient_role,
                :proof_class, :trigger_source, :mailable_class, :subject, :from_identity, :reply_to,
                :test_recipient, :branding_json, :logo_url, :support_json, :html_body, :plain_text_body,
                :runtime_sha, :public_build_id, :render_timestamp, :send_timestamp, :transport_result,
                :content_audit_json, :visual_audit_json, :screenshot_paths, :raw_artifact_sha256
            )'
        );
        $stmt->execute([
            ':run_id' => $row['run_id'] ?? '',
            ':scenario_id' => $row['scenario_id'] ?? '',
            ':correlation_id' => $row['correlation_id'] ?? '',
            ':scenario_name' => $row['scenario_name'] ?? '',
            ':intended_recipient_role' => $row['intended_recipient_role'] ?? '',
            ':proof_class' => $row['proof_class'] ?? '',
            ':trigger_source' => $row['trigger_source'] ?? '',
            ':mailable_class' => $row['mailable_class'] ?? '',
            ':subject' => $row['subject'] ?? '',
            ':from_identity' => $row['from_identity'] ?? '',
            ':reply_to' => $row['reply_to'] ?? '',
            ':test_recipient' => $row['test_recipient'] ?? '',
            ':branding_json' => $row['branding_json'] ?? '{}',
            ':logo_url' => $row['logo_url'] ?? '',
            ':support_json' => $row['support_json'] ?? '{}',
            ':html_body' => $this->sanitizeBody((string) ($row['html_body'] ?? '')),
            ':plain_text_body' => $this->sanitizeBody((string) ($row['plain_text_body'] ?? '')),
            ':runtime_sha' => $row['runtime_sha'] ?? '',
            ':public_build_id' => $row['public_build_id'] ?? '',
            ':render_timestamp' => $row['render_timestamp'] ?? now()->toIso8601String(),
            ':send_timestamp' => $row['send_timestamp'] ?? null,
            ':transport_result' => $row['transport_result'] ?? '',
            ':content_audit_json' => $row['content_audit_json'] ?? '{}',
            ':visual_audit_json' => $row['visual_audit_json'] ?? '{}',
            ':screenshot_paths' => $row['screenshot_paths'] ?? '',
            ':raw_artifact_sha256' => $row['raw_artifact_sha256'] ?? '',
        ]);
    }

    public function updateTransport(string $correlationId, string $transport): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE snapshots SET transport_result = :tr, send_timestamp = :ts WHERE correlation_id = :id'
        );
        $stmt->execute([
            ':tr' => $transport,
            ':ts' => now()->toIso8601String(),
            ':id' => $correlationId,
        ]);
    }

    public function correlationExists(string $correlationId): bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM snapshots WHERE correlation_id = :id LIMIT 1');
        $stmt->execute([':id' => $correlationId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->pdo()->query('SELECT * FROM snapshots ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    protected function sanitizeBody(string $body): string
    {
        $redacted = preg_replace(
            '/\b(?:otp|token|password|secret|code)\s*[:=]\s*[A-Za-z0-9._-]{4,}\b/i',
            '[REDACTED]',
            $body,
        ) ?? $body;

        return preg_replace('/\b\d{6}\b/', '[REDACTED-CODE]', $redacted) ?? $redacted;
    }

    protected function ensureSchema(): void
    {
        $pdo = $this->pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                run_id TEXT,
                scenario_id TEXT,
                correlation_id TEXT UNIQUE,
                scenario_name TEXT,
                intended_recipient_role TEXT,
                proof_class TEXT,
                trigger_source TEXT,
                mailable_class TEXT,
                subject TEXT,
                from_identity TEXT,
                reply_to TEXT,
                test_recipient TEXT,
                branding_json TEXT,
                logo_url TEXT,
                support_json TEXT,
                html_body TEXT,
                plain_text_body TEXT,
                runtime_sha TEXT,
                public_build_id TEXT,
                render_timestamp TEXT,
                send_timestamp TEXT,
                transport_result TEXT,
                content_audit_json TEXT,
                visual_audit_json TEXT,
                screenshot_paths TEXT,
                raw_artifact_sha256 TEXT
            )'
        );
    }

    protected function pdo(): PDO
    {
        $pdo = new PDO('sqlite:'.$this->path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
