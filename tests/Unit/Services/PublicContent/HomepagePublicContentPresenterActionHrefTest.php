<?php

namespace Tests\Unit\Services\PublicContent;

use App\Services\PublicContent\HomepagePublicContentPresenter;
use ReflectionMethod;
use Tests\TestCase;

class HomepagePublicContentPresenterActionHrefTest extends TestCase
{
    public function test_resolve_action_href_keeps_relative_and_tel_without_private_app_url(): void
    {
        $presenter = $this->app->make(HomepagePublicContentPresenter::class);
        $method = new ReflectionMethod(HomepagePublicContentPresenter::class, 'resolveActionHref');
        $method->setAccessible(true);

        $this->assertSame('/support', $method->invoke($presenter, '/support', ''));
        $this->assertSame('tel:+923111222427', $method->invoke($presenter, 'tel:+923111222427', ''));
        $this->assertSame('/support', $method->invoke($presenter, 'http://127.0.0.1:8088/support', ''));
        $this->assertSame(
            'tel:+923111222427',
            $method->invoke($presenter, 'http://127.0.0.1:8088/tel:+923111222427', ''),
        );
        $this->assertSame('tel:03111222427', $method->invoke($presenter, '', '0311 1222427'));
    }
}
