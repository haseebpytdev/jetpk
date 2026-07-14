<?php

namespace App\Http\Requests\Admin;

use App\Services\Agencies\FooterSettingsPresenter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFooterSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_enabled' => ['nullable', 'boolean'],
            'footer_logo' => ['nullable', 'image', 'max:5120'],
            'brand.name' => ['nullable', 'string', 'max:255'],
            'brand.description' => ['nullable', 'string', 'max:3000'],
            'brand.use_brand_logo' => ['nullable', 'boolean'],
            'brand.show_logo' => ['nullable', 'boolean'],
            'support_card.is_enabled' => ['nullable', 'boolean'],
            'support_card.title' => ['nullable', 'string', 'max:120'],
            'support_card.subtitle' => ['nullable', 'string', 'max:255'],
            'support_card.icon' => ['nullable', 'string', 'max:40'],
            'contact.is_enabled' => ['nullable', 'boolean'],
            'contact.heading' => ['nullable', 'string', 'max:120'],
            'contact.address' => ['nullable', 'string', 'max:2000'],
            'contact.phone' => ['nullable', 'string', 'max:100'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.whatsapp' => ['nullable', 'string', 'max:100'],
            'contact.whatsapp_label' => ['nullable', 'string', 'max:80'],
            'contact.city' => ['nullable', 'string', 'max:120'],
            'contact.show_phone' => ['nullable', 'boolean'],
            'contact.show_email' => ['nullable', 'boolean'],
            'contact.show_whatsapp' => ['nullable', 'boolean'],
            'contact.show_address' => ['nullable', 'boolean'],
            'contact.show_city' => ['nullable', 'boolean'],
            'bottom_bar.copyright' => ['nullable', 'string', 'max:500'],
            'bottom_bar.disclaimer' => ['nullable', 'string', 'max:500'],
            'bottom_bar.powered_by_label' => ['nullable', 'string', 'max:120'],
            'bottom_bar.powered_by_url' => ['nullable', 'string', 'max:500'],
            'bottom_bar.show_trust_badges' => ['nullable', 'boolean'],
            'bottom_bar.show_legal_links' => ['nullable', 'boolean'],
            'style.background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'style.bottom_bar_background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'style.text_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'style.heading_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'style.link_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'style.link_hover_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'style.accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'style.spacing' => ['nullable', Rule::in(FooterSettingsPresenter::SPACING_OPTIONS)],
            'style.show_support_card' => ['nullable', 'boolean'],
            'style.show_social' => ['nullable', 'boolean'],
            'style.columns' => ['nullable', 'integer', Rule::in([4, 5])],
            'menu_sections' => ['nullable', 'array'],
            'menu_sections.*.heading' => ['nullable', 'string', 'max:120'],
            'menu_sections.*.is_enabled' => ['nullable', 'boolean'],
            'menu_sections.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'menu_sections.*.items' => ['nullable', 'array'],
            'menu_sections.*.items.*.label' => ['nullable', 'string', 'max:120'],
            'menu_sections.*.items.*.url' => ['nullable', 'string', 'max:500'],
            'menu_sections.*.items.*.is_enabled' => ['nullable', 'boolean'],
            'menu_sections.*.items.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'bottom_bar.legal_links' => ['nullable', 'array'],
            'bottom_bar.legal_links.*.label' => ['nullable', 'string', 'max:120'],
            'bottom_bar.legal_links.*.url' => ['nullable', 'string', 'max:500'],
            'bottom_bar.legal_links.*.is_enabled' => ['nullable', 'boolean'],
            'bottom_bar.legal_links.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'bottom_bar.trust_badges' => ['nullable', 'array'],
            'bottom_bar.trust_badges.*.label' => ['nullable', 'string', 'max:80'],
            'bottom_bar.trust_badges.*.is_enabled' => ['nullable', 'boolean'],
            'bottom_bar.trust_badges.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'social' => ['nullable', 'array'],
            'social.*.url' => ['nullable', 'string', 'max:500'],
            'social.*.is_enabled' => ['nullable', 'boolean'],
        ];
    }
}
