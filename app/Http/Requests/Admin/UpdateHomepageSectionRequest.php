<?php

namespace App\Http\Requests\Admin;

use App\Services\Agencies\HomepageSectionPresenter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHomepageSectionRequest extends FormRequest
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
        $section = (string) $this->route('section');
        $iconRule = Rule::in(array_keys(HomepageSectionPresenter::ICON_CLASSES));

        $base = [
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_enabled' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
            'content' => ['nullable', 'string'],
        ];

        $presenter = app(HomepageSectionPresenter::class);

        if ($presenter->isHeroSection($section)) {
            return array_merge($base, [
                'content' => ['prohibited'],
                'badge' => ['nullable', 'string', 'max:255'],
                'subtitle' => ['nullable', 'string', 'max:5000'],
            ]);
        }

        if (! $presenter->isStructuredSection($section)) {
            return $base;
        }

        return array_merge($base, [
            'content' => ['prohibited'],
            'items' => ['nullable', 'array'],
            'items.*.item_key' => ['nullable', 'string', 'max:50'],
            'items.*.is_enabled' => ['nullable', 'boolean'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'items.*.value' => ['nullable', 'string', 'max:100'],
            'items.*.label' => ['nullable', 'string', 'max:255'],
            'items.*.icon' => ['nullable', 'string', $iconRule],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.text' => ['nullable', 'string', 'max:2000'],
            'items.*.from' => ['nullable', 'string', 'size:3'],
            'items.*.to' => ['nullable', 'string', 'size:3'],
            'items.*.depart' => ['nullable', 'date'],
            'items.*.airline' => ['nullable', 'string', 'max:255'],
            'items.*.airline_code' => ['nullable', 'string', 'max:10'],
            'items.*.baggage' => ['nullable', 'string', 'max:255'],
            'items.*.badge' => ['nullable', 'string', 'max:100'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.refundable' => ['nullable', 'boolean'],
            'items.*.button_label' => ['nullable', 'string', 'max:100'],
            'items.*.button_url' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
