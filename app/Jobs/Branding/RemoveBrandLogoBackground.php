<?php

namespace App\Jobs\Branding;

use App\Models\BrandingAssetProcess;
use App\Services\Media\BackgroundRemovalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RemoveBrandLogoBackground implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public readonly int $processId,
    ) {}

    public function handle(BackgroundRemovalService $service): void
    {
        $process = BrandingAssetProcess::query()->find($this->processId);
        if ($process === null) {
            return;
        }

        $service->process($process);
    }
}
