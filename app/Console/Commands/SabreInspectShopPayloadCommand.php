<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\Diagnostics\SabreInspectSanitizer;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer;
use App\Services\Suppliers\Sabre\SabreFlightSearchRequestBuilder;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;

class SabreInspectShopPayloadCommand extends Command
{
    public const PRODUCTION_READONLY_CONFIRM_PHRASE = 'READONLY-SHOP-PAYLOAD-PREVIEW';

    public const PRODUCTION_CERT_SEND_CONFIRM_PHRASE = 'READONLY-CERT-SHOP-SEND';

    protected $signature = 'sabre:inspect-shop-payload
                            {--from=LHE : Origin airport code}
                            {--to=DXB : Destination airport code}
                            {--date=2026-06-07 : Departure date YYYY-MM-DD}
                            {--adults=1 : Adult passenger count}
                            {--cabin=economy : Cabin class}
                            {--connection= : Supplier connection ID (Sabre); defaults to first Sabre connection}
                            {--variant=current : Payload shape: current (production builder) or minimal (BFM v4 minimal OTA only)}
                            {--confirm= : Production: READONLY-SHOP-PAYLOAD-PREVIEW (preview) or READONLY-CERT-SHOP-SEND (CERT --send only)}
                            {--send : Perform a real HTTPS shop request (uses stored credentials)}
                            {--summary : With --send on success: print key digest and normalization diagnostics only (no raw body)}';

    protected $description = 'Preview sanitized Sabre shop JSON (local/testing; production read-only with --confirm=READONLY-SHOP-PAYLOAD-PREVIEW; CERT --send with --confirm=READONLY-CERT-SHOP-SEND)';

    public function handle(SabreFlightSearchRequestBuilder $builder, SabreClient $client, SabreFlightSearchNormalizer $normalizer): int
    {
        $gate = $this->resolveGate();
        if ($gate === null) {
            return self::FAILURE;
        }

        $connectionId = $this->option('connection');
        $query = SupplierConnection::query()->where('provider', SupplierProvider::Sabre);

        if ($connectionId !== null && $connectionId !== '') {
            $query->whereKey((int) $connectionId);
        }

        $connection = $query->orderBy('id')->first();

        if ($connection === null) {
            $this->components->error('No Sabre supplier connection found. Create one in API settings or pass --connection=');

            return self::FAILURE;
        }

        if ($this->option('send')) {
            if ($gate['production_readonly_confirmed']) {
                $this->components->error('Production live send is not allowed by this command.');

                return self::FAILURE;
            }

            if ($gate['production_cert_send_confirmed'] && ! SabreInspectGate::shopPayloadCertSendAllowed($connection)) {
                $reason = SabreInspectGate::shopPayloadCertSendBlockReason($connection) ?? 'blocked';
                $this->components->error('Production CERT shop --send is not allowed ('.$reason.').');

                return self::FAILURE;
            }
        }

        $variant = strtolower(trim((string) $this->option('variant')));
        if (! in_array($variant, ['current', 'minimal'], true)) {
            $this->components->error('Invalid --variant; use current or minimal.');

            return self::FAILURE;
        }

        $request = FlightSearchRequestData::fromArray([
            'origin' => strtoupper((string) $this->option('from')),
            'destination' => strtoupper((string) $this->option('to')),
            'depart_date' => (string) $this->option('date'),
            'adults' => max(1, (int) $this->option('adults')),
            'cabin' => (string) $this->option('cabin'),
            'currency' => 'USD',
        ]);

        $payload = $builder->buildInspectShopPayload($request, $connection, $variant);
        $masked = SabreInspectSanitizer::maskShopPayload($payload);

        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $shopPath = (string) config('suppliers.sabre.shop_path', '/v4/offers/shop');
        $host = parse_url(str_contains($base, '://') ? $base : 'https://'.$base, PHP_URL_HOST);

        if ($gate['production_readonly_confirmed']) {
            $this->printProductionPreviewSafetyLines();
        } elseif ($gate['production_cert_send_confirmed'] && $this->option('send')) {
            $this->printProductionCertSendSafetyLines($host, $shopPath);
        }

        $this->line('connection_id='.$connection->id);
        $this->line('variant='.$variant);
        $this->line('branded_fares_search_enabled='.($builder->brandedFareSearchQualifiersEnabled() ? 'true' : 'false'));
        $this->line('branded_fares_probe_enabled='.(config('suppliers.sabre.branded_fares_probe_enabled') ? 'true' : 'false'));
        $this->line('branded_fares_request_variant='.$builder->brandedFareRequestVariant());
        $this->line('branded_fare_qualifier_added='.($builder->payloadIncludesBrandedFareSearchQualifiers($payload) ? 'true' : 'false'));
        $this->line('branded_fare_qualifier_path='.$builder->brandedFareQualifierPath());
        $this->line('branded_fare_indicators_keys='.json_encode($builder->brandedFareIndicatorKeys($payload), JSON_UNESCAPED_SLASHES));
        $this->line('iati_alignment_profile='.($builder->usesIatiAlignmentProfile() ? 'true' : 'false'));
        $this->line('endpoint_host='.(is_string($host) && $host !== '' ? $host : 'unknown'));
        $this->line('endpoint_path='.$shopPath);
        $sendRequested = (bool) $this->option('send');
        $this->line('live_supplier_call_attempted='.($sendRequested ? 'true' : 'false'));
        $this->line('payload_preview_only='.($sendRequested ? 'false' : 'true'));
        $this->newLine();
        $this->line('sanitized_payload_preview=');
        $this->line(json_encode($masked, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (! $this->option('send')) {
            $this->newLine();
            if ($gate['production_readonly_confirmed']) {
                $this->comment('Read-only payload preview only; no Sabre network call.');
            } elseif ($gate['production_cert_send_confirmed']) {
                $this->comment('CERT send confirm accepted; pass --send to POST the shop endpoint (probe flag must be ON).');
            } else {
                $this->comment('Omit network call. Pass --send to POST the real shop endpoint (credentials required).');
            }

            return self::SUCCESS;
        }

        $this->newLine();
        $this->comment('Sending shop request...');

        try {
            $response = $client->postShopPayload($connection, $payload);

            $this->line('http_status='.$response->status());

            $json = $response->json();
            if ($response->successful()) {
                if ($this->option('summary') && is_array($json)) {
                    $this->newLine();
                    $this->line('response_key_digest=');
                    $this->line(json_encode($normalizer->groupedResponseKeyDigest($json), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    $this->newLine();
                    $this->line('structure_diagnostics=');
                    $this->line(json_encode($normalizer->inventorySummary($json), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    $this->newLine();
                    $inspectRequest = FlightSearchRequestData::fromArray([
                        'origin' => strtoupper((string) $this->option('from')),
                        'destination' => strtoupper((string) $this->option('to')),
                        'depart_date' => (string) $this->option('date'),
                        'adults' => max(1, (int) $this->option('adults')),
                        'cabin' => (string) $this->option('cabin'),
                        'currency' => 'USD',
                    ]);
                    $offers = $normalizer->normalize($json, $connection, $inspectRequest);
                    $this->line('outcome_diagnostics=');
                    $this->line(json_encode($normalizer->normalizationOutcomeDiagnostics($offers), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    $this->newLine();
                    $this->line('branded_fares_outcome_counts=');
                    $this->line(json_encode($normalizer->brandedFaresOutcomeCounts($offers), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    $this->newLine();
                    $this->line('brand_field_paths_observed=');
                    $this->line(json_encode(SabreFlightSearchNormalizer::BRAND_FIELD_PATHS_OBSERVED, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    $this->newLine();
                    $this->line('branded_fares_probe_diagnostics=');
                    $this->line(json_encode($normalizer->brandedFaresProbeDiagnostics($json, $offers), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } else {
                    $this->line('response_summary=success (body omitted; pass --summary with --send for key digest and diagnostics)');
                }

                return self::SUCCESS;
            }

            $safe = SabreInspectSanitizer::sanitizeErrorBody(is_array($json) ? $json : null);
            $this->line('error_summary='.json_encode($safe, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->components->error('Request failed (exception details omitted; avoid echoing messages that may contain URLs or tokens).');

            return self::FAILURE;
        }
    }

    /**
     * @return array{production_readonly_confirmed: bool, production_cert_send_confirmed: bool}|null Gate context, or null when blocked.
     */
    protected function resolveGate(): ?array
    {
        if (SabreInspectGate::allowed()) {
            return [
                'production_readonly_confirmed' => false,
                'production_cert_send_confirmed' => false,
            ];
        }

        $env = (string) config('app.env', 'production');
        if ($env !== 'production') {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return null;
        }

        $confirm = trim((string) $this->option('confirm'));
        if ($confirm === self::PRODUCTION_READONLY_CONFIRM_PHRASE) {
            return [
                'production_readonly_confirmed' => true,
                'production_cert_send_confirmed' => false,
            ];
        }

        if ($confirm === self::PRODUCTION_CERT_SEND_CONFIRM_PHRASE) {
            return [
                'production_readonly_confirmed' => false,
                'production_cert_send_confirmed' => true,
            ];
        }

        if ($confirm === '') {
            $this->components->error(
                'Production requires --confirm='.self::PRODUCTION_READONLY_CONFIRM_PHRASE.' for read-only shop payload preview, or --confirm='.self::PRODUCTION_CERT_SEND_CONFIRM_PHRASE.' for CERT --send.'
            );
        } else {
            $this->components->error('Invalid --confirm phrase for production shop payload inspect.');
        }

        return null;
    }

    protected function printProductionPreviewSafetyLines(): void
    {
        $this->line('app_env=production');
        $this->newLine();
    }

    /**
     * @param  mixed  $host
     */
    protected function printProductionCertSendSafetyLines($host, string $shopPath): void
    {
        $this->line('app_env=production');
        $this->line('cert_shop_send=true');
        $this->newLine();
    }
}
