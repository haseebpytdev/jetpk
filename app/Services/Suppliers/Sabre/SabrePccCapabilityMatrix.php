<?php

namespace App\Services\Suppliers\Sabre;

/**
 * Compatibility stub (Phase S2H). Use {@see Diagnostics\SabrePccCapabilityMatrix}.
 *
 * @deprecated
 */
class_alias(
    Diagnostics\SabrePccCapabilityMatrix::class,
    __NAMESPACE__.'\\SabrePccCapabilityMatrix',
);

class_alias(
    Diagnostics\SabrePccCapabilityCallBudget::class,
    __NAMESPACE__.'\\SabrePccCapabilityCallBudget',
);
