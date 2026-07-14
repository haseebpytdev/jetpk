<?php

namespace App\Http\Requests\Admin;

use App\Models\CmsPage;

class UpdateCmsPageRequest extends CmsPageRequest
{
    protected function slugUniqueRule(): mixed
    {
        /** @var CmsPage $cmsPage */
        $cmsPage = $this->route('cmsPage');

        return $this->slugUniqueRuleForTable('cms_pages', 'slug', $cmsPage->id);
    }
}
