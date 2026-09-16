# CMS-CONF content gate

## Finding
Production homepage renders eyebrow/headline containing `[CMS-CONF-1789016764942]`.
This is CMS-published content (PublicHero renders `hero.eyebrow` / `hero.headline` as stored).

## Not a code defect
No repository source injects this marker. Exact-SHA deploy of golden/CMS media commits will not remove it.

## Required post-deploy procedure
1. Login admin QA account
2. `/admin/page-settings/home` — edit hero eyebrow/headline to approved JetPakistan copy (no CMS-CONF)
3. Save draft → verify draft does not leak on public `/`
4. Publish → confirm `GET /api/public/content/homepage` updated
5. Confirm Next `/` revalidated (homepage-cms tag) without PM2 restart
6. Browser verify marker absent
7. Record before/after in `07-live-uat/`

## Gate
`CMS_CONF_ABSENT_ON_HOMEPAGE` must PASS in live UAT before overall closure PASS.
