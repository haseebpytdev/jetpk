<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkErrorLayoutAudit;
use App\Support\Client\ClientErrorResponseResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;

/**
 * Audits JetPK error shell for duplicate header/footer/panel rendering.
 */
class JetpkErrorShellAuditCommand extends Command
{
    protected $signature = 'jetpk:error-shell-audit';

    protected $description = 'Audit JetPK error pages for single-document rendered HTML (read-only)';

    public function handle(JetpkErrorLayoutAudit $audit): int
    {
        $this->line('Classification: READ-ONLY JetPK error shell audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $result = $audit->run();
        $resolver = app(ClientErrorResponseResolver::class);

        $rows = [
            ['404 renders', $result['error_404_renders'] ? 'pass' : 'fail'],
            ['500 renders', $result['error_500_renders'] ? 'pass' : 'fail'],
            ['single header (404)', $result['single_header'] ? 'pass' : 'fail'],
            ['single footer (404)', $result['single_footer'] ? 'pass' : 'fail'],
            ['single error panel (404)', $result['single_error_panel'] ? 'pass' : 'fail'],
            ['no duplicate brand block', $result['no_duplicate_brand_block'] ? 'pass' : 'fail'],
            ['shell does not extend frontend', ! $result['shell_extends_frontend'] ? 'pass' : 'fail'],
            ['root error views single @extends', $result['root_error_views_single_extends'] ? 'pass' : 'fail'],
        ];

        foreach (ClientErrorResponseResolver::SUPPORTED_CODES as $code) {
            $codeResult = $result['codes'][$code] ?? ['renders' => false, 'issues' => []];
            $rows[] = [
                'rendered '.$code.' single document',
                ($codeResult['renders'] ?? false) && ($codeResult['issues'] ?? []) === [] ? 'pass' : 'fail',
            ];
        }

        $this->table(['check', 'result'], $rows);

        if (View::exists($resolver->resolveView('404'))) {
            $html = view($resolver->resolveView('404'))->render();
            $counts = ClientErrorResponseResolver::countDocumentMarkers($html);
            $this->newLine();
            $this->line('404 rendered markers:');
            $this->line('doctype='.$counts['doctype']);
            $this->line('html='.$counts['html']);
            $this->line('head='.$counts['head']);
            $this->line('body='.$counts['body']);
            $this->line('header='.$counts['header']);
            $this->line('main='.$counts['main']);
            $this->line('footer='.$counts['footer']);
            $this->line('jp-error-panel='.$counts['panel']);
            $this->line('generic_card='.$counts['generic_card']);
        }

        foreach ($result['issues'] as $issue) {
            $this->warn($issue);
        }

        $fail = $result['fail_count'];
        $this->newLine();
        $this->line('fail='.$fail);

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
