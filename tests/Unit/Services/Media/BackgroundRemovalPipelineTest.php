<?php

namespace Tests\Unit\Services\Media;

use App\Data\Media\BackgroundRemovalInput;
use App\Services\Media\ImageTransparencyInspector;
use App\Services\Media\Providers\NullBackgroundRemovalProvider;
use Tests\TestCase;

class BackgroundRemovalPipelineTest extends TestCase
{
    public function test_disabled_provider_returns_safe_failure(): void
    {
        $provider = new NullBackgroundRemovalProvider;
        $result = $provider->remove(new BackgroundRemovalInput(
            absoluteSourcePath: __FILE__,
            sourceMime: 'image/png',
            timeoutSeconds: 5,
        ));

        $this->assertFalse($result->success);
        $this->assertSame('provider_disabled', $result->errorCode);
    }

    public function test_transparency_inspector_reports_opaque_jpeg_as_known(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD not available');
        }

        $path = sys_get_temp_dir().'/jp-test-opaque.jpg';
        $img = imagecreatetruecolor(4, 4);
        imagejpeg($img, $path);
        imagedestroy($img);

        $inspector = new ImageTransparencyInspector;
        $result = $inspector->inspect($path);
        @unlink($path);

        $this->assertTrue($result->known);
        $this->assertTrue($result->isFullyOpaque);
    }
}
