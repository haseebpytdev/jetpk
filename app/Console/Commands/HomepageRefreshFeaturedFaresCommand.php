<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Services\Homepage\HomepageFeaturedFareRefreshService;
use Illuminate\Console\Command;

class HomepageRefreshFeaturedFaresCommand extends Command
{
    protected $signature = 'homepage:refresh-featured-fares {--agency= : Optional agency slug}';

    protected $description = 'Refresh stored homepage featured fare snapshots via flight shopping search';

    public function handle(HomepageFeaturedFareRefreshService $service): int
    {
        $agency = null;
        if (filled($this->option('agency'))) {
            $agency = Agency::query()->where('slug', (string) $this->option('agency'))->first();
            if ($agency === null) {
                $this->error('Agency not found.');

                return self::FAILURE;
            }
        }

        $count = $service->refreshAll($agency);
        $this->info("Refreshed {$count} featured fare route(s).");

        return self::SUCCESS;
    }
}
