<?php

namespace App\Services\Ai\Embed\Adapters\JetPakistan;

use App\Contracts\Ai\Embed\IdentityProvider;

final class JetPakistanIdentityProvider implements IdentityProvider
{
    public function consentSource(): string
    {
        return 'ask_jetpakistan';
    }

    public function leadSource(): string
    {
        return 'ask_jetpakistan';
    }
}
