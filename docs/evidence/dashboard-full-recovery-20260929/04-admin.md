# 04 — Admin recovery (Batch A)

## Action
Forward-recover Admin mutation reachability by bridging Next live sidebar to existing Blade hubs.

## Nav bridges added (target=laravel)
- API Settings → `/admin/api-settings`
- Company Profile → `/admin/settings/branding`
- Homepage CMS → `/admin/settings/homepage`
- CMS Pages → `/admin/cms-pages`
- Managed Pages → `/admin/page-settings` (when route resolvable)
- SEO → `/admin/seo`
- Customer Queries → `/admin/customer-queries`
- Communications → `/admin/settings/communications`
- Markups → `/admin/markups`
- Group Ticketing → `/admin/group-ticketing`
- Login OTP → `/admin/settings/login-otp`
- Ask JetPakistan → `/admin/settings/ai-assistant`
- Go-live → `/admin/go-live-checklist`
- Staff → `/admin/staff`
- Settings Hub → `/admin/settings`

## Still Next (target=dashboard)
Overview, Bookings, Payments, PNRs, Tickets, Deposits, Customers, Agents, Suppliers (read), Users, Reports, Audit, Support.

## Not done in Batch A
Full Next write hubs from owner-uat (API Connections card UI, branding JSON in Next, RBAC write UI). Blade remains mutation authority.
