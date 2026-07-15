<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AirBlueAuditReferenceCodeCommand extends Command
{
    protected $signature = 'airblue:audit-reference-code';

    protected $description = 'Scan local Binham PIA/Hitit reference paths and print NDC-related usage';

    /** @var list<string> */
    private array $paths = [
        'Binham/ota.binham.pk',
        'Binham/Iati_new',
        'Binham/public_html',
        'Binham/iati',
    ];

    /** @var list<string> */
    private array $needles = [
        'CraneNDCService',
        'DoAirShopping',
        'IATA_AirShoppingRQ',
        'IATA_OrderCreateRQ',
        'IATA_OrderRetrieveRQ',
        'IATA_OrderChangeRQ',
        'IATA_OrderReshopRQ',
        'impl.soap.ws.crane.hititcs.com',
        'HititTrait',
        'GetAvailability',
        'CreateBooking',
        'issueTicket',
        'voidBookingRequest',
        'cancelBookingRequest',
        'pia_url',
        'dd(',
        'dump(',
        'var_dump(',
    ];

    public function handle(): int
    {
        $root = base_path();
        foreach ($this->paths as $relative) {
            $full = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $this->line('--- path='.$relative.' exists='.(is_dir($full) ? 'yes' : 'no'));
            if (! is_dir($full)) {
                continue;
            }

            $files = File::allFiles($full);
            $hits = 0;
            foreach ($files as $file) {
                if (! in_array($file->getExtension(), ['php', 'js', 'json', 'xml'], true)) {
                    continue;
                }
                $content = @file_get_contents($file->getPathname());
                if ($content === false) {
                    continue;
                }
                foreach ($this->needles as $needle) {
                    if (str_contains($content, $needle)) {
                        $this->line('  '.$file->getRelativePathname().' → '.$needle);
                        $hits++;
                        break;
                    }
                }
            }
            $this->line('  files_with_hits='.$hits);
        }

        $this->newLine();
        $this->warn('Legacy Crane OTA (HititTrait) is NOT NDC 20.1 — do not copy SOAP payloads.');
        $this->warn('Unsafe patterns to avoid: last_soap_request.xml, env(pia_url), dd()/dump(), raw SOAP errors to users');

        return self::SUCCESS;
    }
}
