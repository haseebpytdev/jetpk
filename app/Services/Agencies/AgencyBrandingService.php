<?php

namespace App\Services\Agencies;

use App\Models\Agency;
use App\Models\AgencyHomepageSection;
use App\Models\AgencyMedia;
use App\Models\AgencySetting;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Branding\BrandDisplayResolver;
use App\Support\Branding\PlatformBrandingResolver;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AgencyBrandingService
{
    /**
     * @var list<string>
     */
    protected const HOMEPAGE_SECTIONS = ['hero', 'trust_metrics', 'feature_cards', 'popular_routes', 'operator_preview', 'why_choose_us'];

    public function getSettingsForAgency(Agency $agency): AgencySetting
    {
        $setting = AgencySetting::query()->firstOrCreate(
            ['agency_id' => $agency->id],
            [
                'display_name' => $agency->name,
                'timezone' => $agency->timezone ?? 'Asia/Karachi',
                'country' => 'Pakistan',
                'currency' => 'PKR',
            ]
        );

        foreach (self::HOMEPAGE_SECTIONS as $index => $sectionKey) {
            AgencyHomepageSection::query()->firstOrCreate(
                ['agency_id' => $agency->id, 'section_key' => $sectionKey],
                ['sort_order' => ($index + 1) * 10, 'is_enabled' => true]
            );
        }

        return $setting;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(
        Agency $agency,
        User $actor,
        array $data,
        ?string $colorScheme = null,
        ?array $slimTopbar = null,
    ): AgencySetting {
        $settings = $this->getSettingsForAgency($agency);
        if ($colorScheme !== null || $slimTopbar !== null || array_key_exists('header_logo_height', $data)) {
            $meta = is_array($settings->meta) ? $settings->meta : [];
            if ($colorScheme !== null) {
                $meta[BrandDisplayResolver::META_COLOR_SCHEME] = $colorScheme;
            }
            if ($slimTopbar !== null) {
                $meta[SlimTopbarPresenter::META_KEY] = $slimTopbar;
            }
            if (array_key_exists('header_logo_height', $data)) {
                $height = $data['header_logo_height'];
                if ($height === null || $height === '') {
                    unset($meta[PlatformBrandingResolver::META_HEADER_LOGO_HEIGHT]);
                } else {
                    $meta[PlatformBrandingResolver::META_HEADER_LOGO_HEIGHT] = PlatformBrandingResolver::clampHeaderLogoHeight((int) $height);
                }
                unset($data['header_logo_height']);
            }
            $settings->meta = $meta;
        }
        $settings->fill($data);
        $settings->save();

        $auditKeys = array_keys($data);
        if ($slimTopbar !== null) {
            $auditKeys[] = SlimTopbarPresenter::META_KEY;
        }
        $this->writeAudit($agency, $actor, 'agency.branding_settings_updated', ['updated_keys' => $auditKeys]);

        return $settings->fresh();
    }

    /**
     * @param  array<string, mixed>  $footerPayload
     */
    public function updateFooterSettings(Agency $agency, User $actor, array $footerPayload, ?string $footerLogoPath = null): AgencySetting
    {
        $settings = $this->getSettingsForAgency($agency);
        $meta = is_array($settings->meta) ? $settings->meta : [];
        $meta[FooterSettingsPresenter::META_KEY] = $footerPayload;
        $settings->meta = $meta;

        if ($footerLogoPath !== null) {
            $settings->footer_logo_path = $footerLogoPath;
        }

        if (! empty($footerPayload['brand']['description'])) {
            $settings->footer_about = (string) $footerPayload['brand']['description'];
        }
        if (! empty($footerPayload['bottom_bar']['copyright'])) {
            $settings->footer_copyright = (string) $footerPayload['bottom_bar']['copyright'];
        }

        $settings->save();

        $this->writeAudit($agency, $actor, 'agency.footer_settings_updated', ['footer' => 'updated']);

        return $settings->fresh();
    }

    /**
     * @param  array{plain: string, html_override: string, html_active: bool, updated_at: string}  $aboutUsPayload
     */
    public function updateAboutUsSettings(Agency $agency, User $actor, array $aboutUsPayload): AgencySetting
    {
        $settings = $this->getSettingsForAgency($agency);
        $meta = is_array($settings->meta) ? $settings->meta : [];
        $meta[AboutUsContentPresenter::META_KEY] = $aboutUsPayload;
        $settings->meta = $meta;
        $settings->save();

        $this->writeAudit($agency, $actor, 'agency.about_us_settings_updated', ['about_us' => 'updated']);

        return $settings->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateHomepageSection(Agency $agency, User $actor, string $sectionKey, array $data): AgencyHomepageSection
    {
        if (! in_array($sectionKey, self::HOMEPAGE_SECTIONS, true)) {
            throw ValidationException::withMessages(['section' => 'Invalid section key.']);
        }

        $section = AgencyHomepageSection::query()->firstOrCreate(
            ['agency_id' => $agency->id, 'section_key' => $sectionKey],
            ['sort_order' => 100, 'is_enabled' => true]
        );
        $section->fill($data);
        $section->save();

        $this->writeAudit($agency, $actor, 'agency.homepage_section_updated', ['section' => $sectionKey, 'updated_keys' => array_keys($data)]);

        return $section->fresh();
    }

    public function uploadMedia(Agency $agency, User $actor, UploadedFile $file, string $collection, ?string $altText): AgencyMedia
    {
        $this->validateImage($file);

        $folder = match ($collection) {
            'branding' => "agencies/{$agency->id}/branding",
            'homepage' => "agencies/{$agency->id}/homepage",
            default => "agencies/{$agency->id}/media",
        };

        $path = $file->store($folder, 'public');
        $media = AgencyMedia::query()->create([
            'agency_id' => $agency->id,
            'uploaded_by' => $actor->id,
            'collection' => $collection,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'alt_text' => $altText,
        ]);

        $this->writeAudit($agency, $actor, 'agency.media_uploaded', [
            'agency_media_id' => $media->id,
            'collection' => $collection,
            'file_path' => $path,
        ]);

        return $media;
    }

    public function deleteMedia(AgencyMedia $media, User $actor): void
    {
        if ($media->file_path !== null && Storage::disk('public')->exists($media->file_path)) {
            Storage::disk('public')->delete($media->file_path);
        }
        $agencyId = $media->agency_id;
        $mediaId = $media->id;
        $media->delete();

        $this->writeAudit(Agency::query()->findOrFail($agencyId), $actor, 'agency.media_deleted', [
            'agency_media_id' => $mediaId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicBrandingPayload(Agency $agency): array
    {
        try {
            $settings = null;
            if (Schema::hasTable('agency_settings')) {
                $settings = AgencySetting::query()->where('agency_id', $agency->id)->first();
            }

            $sections = collect();
            if (Schema::hasTable('agency_homepage_sections')) {
                /** @var Collection<int, AgencyHomepageSection> $sections */
                $sections = AgencyHomepageSection::query()
                    ->where('agency_id', $agency->id)
                    ->orderBy('sort_order')
                    ->get()
                    ->keyBy('section_key');
            }

            return [
                'has_db_settings' => $settings !== null,
                'settings' => $settings,
                'sections' => $sections,
                'public_media_base' => Storage::disk('public')->url('/'),
            ];
        } catch (Throwable $e) {
            Log::warning('Public branding payload fallback used.', [
                'agency_id' => $agency->id,
                'error' => class_basename($e),
                'message' => app()->environment('production') ? 'suppressed' : $e->getMessage(),
            ]);

            return [
                'has_db_settings' => false,
                'settings' => null,
                'sections' => collect(),
                'public_media_base' => '/storage',
            ];
        }
    }

    protected function validateImage(UploadedFile $file): void
    {
        if (! in_array($file->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'webp', 'svg', 'ico'], true)) {
            throw ValidationException::withMessages(['file' => 'Only image files are allowed.']);
        }
        if ($file->getSize() > (5 * 1024 * 1024)) {
            throw ValidationException::withMessages(['file' => 'Image must be 5MB or less.']);
        }
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    protected function writeAudit(Agency $agency, User $actor, string $action, array $newValues): void
    {
        AuditLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => Agency::class,
            'auditable_id' => $agency->id,
            'properties' => [
                'old_values' => [],
                'new_values' => SensitiveDataRedactor::redact($newValues),
            ],
        ]);
    }
}
