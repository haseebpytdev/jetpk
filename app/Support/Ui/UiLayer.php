<?php

namespace App\Support\Ui;

/**
 * Registered UI override layer (CSS/JS bundle loaded after base assets).
 */
final readonly class UiLayer
{
    /**
     * @param  list<string>  $contexts
     * @param  list<string>  $css
     * @param  list<string>  $js
     * @param  list<string>  $suppliers
     */
    public function __construct(
        public string $key,
        public array $contexts,
        public int $order,
        public bool $defaultEnabled,
        public array $css,
        public array $js,
        public string $description,
        public string $rollback,
        public array $suppliers = [],
    ) {}

    public function envVarName(): string
    {
        return 'OTA_UI_LAYER_'.strtoupper(str_replace('-', '_', $this->key));
    }
}
