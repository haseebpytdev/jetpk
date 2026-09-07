<?php

namespace App\Services\Notifications;

final class ResolvedNotificationRouteSet
{
    /**
     * @param  list<ResolvedNotificationRoute>  $routes
     */
    public function __construct(
        public array $routes,
        public bool $fallback,
        public string $source,
    ) {}

    /**
     * @return list<string>
     */
    public function audiences(): array
    {
        $audiences = [];
        foreach ($this->routes as $route) {
            $audiences[] = $route->audience;
        }

        return array_values(array_unique($audiences));
    }
}
