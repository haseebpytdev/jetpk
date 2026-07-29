# Branding and Legacy Leakage Audit (JP-FE-13)

## Runtime public output

- JetPakistan branding only in header, footer, metadata, error pages
- Social links point to `facebook.com/jetpakistancom` and `instagram.com/jetpakistanofficial`
- Contact channels from `ClientGlobalContactResolver` (not `config/ota-client.php` demo phones)

## Removed from public navigation

- Placeholder routes: `/hotels`, `/offers`, `/careers`, `/press`, `/investors`, `/travel-services/*`
- Generic social URLs (`facebook.com`, `x.com`, etc.)

## Intentionally retained internal identifiers

- `jetpk` client key in configuration (not visible in public UI)
- Legacy Laravel redirect `/contact` → `/about-us` (Next owns `/contact`)

## Not found in public runtime

- Parwaaz, Master, TourNest, Asif Travels, Easy Ticket, YD Travel, ota.haseebasif.com in navigation or public components
