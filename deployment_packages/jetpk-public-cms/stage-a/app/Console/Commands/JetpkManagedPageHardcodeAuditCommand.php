<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkManagedPageHardcodeAuditService;
use Illuminate\Console\Command;

class JetpkManagedPageHardcodeAuditCommand extends Command
{
    protected $signature = 'jetpk:managed-page-hardcode-audit';

    protected $description = 'Read-only scan of managed JetPK public views for client-specific runtime literals';

    public function handle(JetpkManagedPageHardcodeAuditService $service): int
    {
        $this->line('Classification: READ-ONLY managed page hardcode audit.');
        $this->newLine();

        $result = $service->run();

        $this->table(['metric', 'value'], [
            ['managed_files_scanned', (string) $result['managed_files_scanned']],
            ['client_content_literals_found', (string) $result['client_content_literals_found']],
            ['allowed_platform_literals', (string) $result['allowed_platform_literals']],
            ['unapproved_runtime_fallbacks', (string) $result['unapproved_runtime_fallbacks']],
            ['hardcoded_contact_details', (string) $result['hardcoded_contact_details']],
            ['hardcoded_legal_copy', (string) $result['hardcoded_legal_copy']],
            ['hardcoded_navigation_labels', (string) $result['hardcoded_navigation_labels']],
            ['hardcoded_metadata', (string) $result['hardcoded_metadata']],
        ]);

        $this->newLine();
        $this->line('JSON: '.$result['path']);

        $fail = ($result['unapproved_runtime_fallbacks'] ?? 0) > 0
            || ($result['hardcoded_contact_details'] ?? 0) > 0
            || ($result['hardcoded_legal_copy'] ?? 0) > 0;

        return $fail ? self::FAILURE : self::SUCCESS;
    }
}
