<?php

namespace Tests\Unit\Support\Client;

use App\Support\Client\ReservedClientPreviewSlugs;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReservedClientPreviewSlugsConstraintTest extends TestCase
{
    public function test_reserved_first_segments_do_not_bind_client_slug_parameter(): void
    {
        foreach (['v1', 'v2', 'admin', 'login'] as $slug) {
            $this->assertTrue(ReservedClientPreviewSlugs::isReserved($slug));

            try {
                Route::getRoutes()->match(\Illuminate\Http\Request::create('/'.$slug.'/home', 'GET'));
                $this->fail('Expected reserved slug /'.$slug.'/home to miss client.preview.* routes.');
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_non_reserved_slug_still_matches_preview_home_route_when_registered(): void
    {
        if (! Route::has('client.preview.home')) {
            $this->markTestSkipped('client.preview.home is not registered in parity mode.');
        }

        $matched = Route::getRoutes()->match(
            \Illuminate\Http\Request::create('/preview-client/home', 'GET'),
        );

        $this->assertSame('client.preview.home', $matched->getName());
        $this->assertSame('preview-client', $matched->parameter('clientSlug'));
    }
}
