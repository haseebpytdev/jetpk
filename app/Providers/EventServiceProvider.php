<?php

namespace App\Providers;

use App\Listeners\Auth\SendEmailVerificationNotificationBestEffort;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Replaces Laravel's default Registered → SendEmailVerificationNotification wiring
 * with commit-safe, non-throwing delivery.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotificationBestEffort::class,
        ],
    ];

    /**
     * Prevent Framework EventServiceProvider from also attaching the throwing stock listener.
     *
     * Laravel's default configureEmailVerification() re-attaches the stock listener unless
     * Registered is mapped specifically to SendEmailVerificationNotification — which we do not want.
     */
    protected function configureEmailVerification(): void
    {
        // Stock Illuminate\Auth\Listeners\SendEmailVerificationNotification must not run.
    }
}
