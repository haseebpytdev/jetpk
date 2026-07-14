<?php

namespace App\Http\Requests\Admin;

use App\Models\CmsPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

abstract class CmsPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('title')) {
            $this->merge([
                'slug' => Str::slug($this->string('title')->toString()),
            ]);
        }

        if ($this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug($this->string('slug')->toString()),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $this->slugUniqueRule()],
            'content' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'robots' => ['required', Rule::in([CmsPage::ROBOTS_INDEX, CmsPage::ROBOTS_NOINDEX])],
            'status' => ['required', Rule::in([
                CmsPage::STATUS_DRAFT,
                CmsPage::STATUS_ACTIVE,
                CmsPage::STATUS_ARCHIVED,
            ])],
            'show_in_footer' => ['sometimes', 'boolean'],
            'footer_group' => ['nullable', Rule::in(CmsPage::FOOTER_GROUPS)],
            'footer_label' => ['nullable', 'string', 'max:120'],
            'footer_sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'open_in_new_tab' => ['sometimes', 'boolean'],
        ];
    }

    abstract protected function slugUniqueRule(): mixed;

    protected function slugUniqueRuleForTable(string $table, string $column, ?int $ignoreId = null): mixed
    {
        $rule = Rule::unique($table, $column);

        if ($ignoreId !== null) {
            $rule = $rule->ignore($ignoreId);
        }

        return $rule;
    }
}
