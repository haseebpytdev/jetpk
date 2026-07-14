<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class IatiAuditDocsCommand extends Command
{
    protected $signature = 'iati:audit-docs {--save-openapi : Save OpenAPI JSON if found}';

    protected $description = 'Probe IATI docs and OpenAPI schema accessibility';

    /** @var list<string> */
    private array $schemaPaths = [
        'http://testapi.iati.com/rest/flight/v2/docs/swagger.json',
        'http://testapi.iati.com/rest/flight/v2/docs/openapi.json',
        'http://testapi.iati.com/rest/flight/v2/openapi.json',
        'http://testapi.iati.com/rest/flight/v2/swagger.json',
        'http://testapi.iati.com/rest/flight/v2/api-docs',
    ];

    /** @var list<array{method: string, path: string}> */
    private array $endpointMatrix = [
        ['method' => 'GET', 'path' => '/rest/auth/token'],
        ['method' => 'GET', 'path' => '/rest/flight/v2/test/ping'],
        ['method' => 'POST', 'path' => '/rest/flight/v2/airport'],
        ['method' => 'POST', 'path' => '/rest/flight/v2/search'],
        ['method' => 'POST', 'path' => '/rest/flight/v2/fare'],
        ['method' => 'POST', 'path' => '/rest/flight/v2/book'],
        ['method' => 'POST', 'path' => '/rest/flight/v2/option'],
        ['method' => 'POST', 'path' => '/rest/flight/v2/option/{orderId}/book'],
        ['method' => 'GET', 'path' => '/rest/flight/v2/book/{orderId}/cancel'],
        ['method' => 'GET', 'path' => '/rest/flight/v2/option/{orderId}/cancel'],
        ['method' => 'GET', 'path' => '/rest/flight/v2/order/{orderId}'],
        ['method' => 'POST', 'path' => '/rest/flight/v2/order'],
        ['method' => 'GET', 'path' => '/rest/flight/v2/balance'],
    ];

    public function handle(): int
    {
        $docsUrl = 'http://testapi.iati.com/rest/flight/v2/docs';
        $this->line('docs_url='.$docsUrl);

        try {
            $docsResponse = Http::timeout(15)->get($docsUrl);
            $this->line('docs_http_status='.$docsResponse->status());
        } catch (\Throwable $e) {
            $this->warn('docs_fetch_failed='.$e->getMessage());
        }

        $foundSchema = false;
        foreach ($this->schemaPaths as $url) {
            try {
                $response = Http::timeout(10)->get($url);
                $status = $response->status();
                $this->line('schema_probe url='.$url.' status='.$status);
                if ($status >= 200 && $status < 300 && $this->option('save-openapi')) {
                    $body = $response->body();
                    if (str_starts_with(trim($body), '{')) {
                        $target = base_path('docs/providers/iati-openapi-source.json');
                        file_put_contents($target, $body);
                        $this->info('Saved OpenAPI to docs/providers/iati-openapi-source.json');
                        $foundSchema = true;
                    }
                }
            } catch (\Throwable $e) {
                $this->line('schema_probe url='.$url.' error='.$e->getMessage());
            }
        }

        if (! $foundSchema) {
            $this->line('openapi_found=no');
        }

        $this->newLine();
        $this->info('Endpoint matrix:');
        foreach ($this->endpointMatrix as $row) {
            $this->line($row['method'].' '.$row['path']);
        }

        return self::SUCCESS;
    }
}
