# Browser waterfalls

Pre-fix: document → many `/_next/static/chunks/*` (~800ms TTFB cluster) → hydration → parallel `/laravel/groups/search/{facets,data}` + CMS.

Post-fix: document HTML includes result cards; client inventory/facets omitted; CMS secondary only.
