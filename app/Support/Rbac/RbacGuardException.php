<?php

namespace App\Support\Rbac;

use RuntimeException;

final class RbacGuardException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 403,
        public readonly string $codeKey = 'rbac_denied',
    ) {
        parent::__construct($message);
    }
}
