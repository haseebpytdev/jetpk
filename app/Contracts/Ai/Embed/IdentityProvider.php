<?php

namespace App\Contracts\Ai\Embed;

interface IdentityProvider
{
    public function consentSource(): string;

    public function leadSource(): string;
}
