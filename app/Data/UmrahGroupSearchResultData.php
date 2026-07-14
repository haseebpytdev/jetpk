<?php

namespace App\Data;

/**
 * Result envelope for public Umrah group package search.
 */
class UmrahGroupSearchResultData
{
    /**
     * @param  list<UmrahGroupPackageData>  $packages
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public array $packages = [],
        public array $warnings = [],
        public array $meta = [],
        public bool $from_cache = false,
        public bool $from_stale_cache = false,
        public bool $api_unavailable = false,
        public bool $api_disabled = false,
    ) {}
}
