<?php

namespace App\Http\Requests\Admin;

class StoreCmsPageRequest extends CmsPageRequest
{
    protected function slugUniqueRule(): mixed
    {
        return $this->slugUniqueRuleForTable('cms_pages', 'slug');
    }
}
