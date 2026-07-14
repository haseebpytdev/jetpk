<?php

namespace App\Console\Commands;

use App\Services\Media\ImageTransparencyInspector;
use Illuminate\Console\Command;

class JetpkImageTransparencyAuditCommand extends Command
{
    protected $signature = 'jetpk:image-transparency-audit {path}';

    protected $description = 'Read-only transparency inspection for a local image path';

    public function handle(ImageTransparencyInspector $inspector): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return self::FAILURE;
        }

        $result = $inspector->inspect($path);
        $this->line('known='.($result->known ? 'true' : 'false'));
        $this->line('has_alpha_channel='.($result->hasAlphaChannel ? 'true' : 'false'));
        $this->line('has_transparent_pixels='.($result->hasTransparentPixels ? 'true' : 'false'));
        $this->line('transparent_ratio='.$result->transparentPixelRatio);
        $this->line('opaque_ratio='.$result->opaquePixelRatio);
        $this->line('fully_transparent='.($result->isFullyTransparent ? 'true' : 'false'));
        $this->line('fully_opaque='.($result->isFullyOpaque ? 'true' : 'false'));
        if ($result->warning) {
            $this->warn($result->warning);
        }

        return self::SUCCESS;
    }
}
