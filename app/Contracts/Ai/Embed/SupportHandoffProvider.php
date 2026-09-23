<?php

namespace App\Contracts\Ai\Embed;

interface SupportHandoffProvider
{
    public function isEnabled(): bool;
}
