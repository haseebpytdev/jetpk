<?php

namespace App\Contracts\Ai\Embed;

interface SearchProvider
{
    public function isEnabled(): bool;
}
