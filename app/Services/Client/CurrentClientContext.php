<?php

namespace App\Services\Client;

use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileSupplier;
use Illuminate\Database\Eloquent\Collection;

/**
 * Request-scoped runtime client profile context (MC-4 preview, MC-5A default).
 *
 * Preview routes call set() (isPreview=true). All other requests lazily resolve
 * the default profile (config slug or haseeb-master) without marking preview mode.
 */
final class CurrentClientContext
{
    private ?ClientProfile $profile = null;

    private bool $preview = false;

    private bool $defaultResolved = false;

    public function __construct(
        private readonly ClientProfileResolver $resolver,
    ) {}

    public function set(ClientProfile $profile): void
    {
        $this->profile = $profile->relationLoaded('modules')
            && $profile->relationLoaded('suppliers')
            && $profile->relationLoaded('branding')
            ? $profile
            : $profile->load(['modules', 'suppliers', 'branding']);

        $this->preview = true;
        $this->defaultResolved = true;
    }

    public function get(): ?ClientProfile
    {
        $this->ensureDefaultResolved();

        return $this->profile;
    }

    public function slug(): ?string
    {
        $this->ensureDefaultResolved();

        return $this->profile?->slug;
    }

    public function isPreview(): bool
    {
        return $this->preview && $this->profile !== null;
    }

    public function branding(): ?ClientProfileBranding
    {
        $this->ensureDefaultResolved();

        if ($this->profile === null) {
            return null;
        }

        return $this->resolver->brandingFor($this->profile);
    }

    /**
     * @return array<string, bool>
     */
    public function modules(): array
    {
        $this->ensureDefaultResolved();

        if ($this->profile === null) {
            return [];
        }

        return $this->resolver->modulesFor($this->profile);
    }

    /**
     * @return Collection<int, ClientProfileSupplier>
     */
    public function suppliers(): Collection
    {
        $this->ensureDefaultResolved();

        if ($this->profile === null) {
            return new Collection;
        }

        return $this->resolver->suppliersFor($this->profile);
    }

    public function theme(): ?string
    {
        $this->ensureDefaultResolved();

        return $this->profile?->active_frontend_theme;
    }

    public function assetProfile(): ?string
    {
        $this->ensureDefaultResolved();

        return $this->profile?->asset_profile;
    }

    public function clear(): void
    {
        $this->profile = null;
        $this->preview = false;
        $this->defaultResolved = false;
    }

    private function ensureDefaultResolved(): void
    {
        if ($this->defaultResolved || $this->preview) {
            return;
        }

        $this->defaultResolved = true;
        $this->profile = $this->resolver->resolveDefault();
    }
}
