# JP-FRONTEND Dialog Toast Contract

## Dialog (`components/ui/Dialog.tsx`)

- Focus trap, Escape close, restore focus
- Accessible title/description
- Backdrop inertness, body scroll lock
- Reduced-motion: no enter animation

## Drawer (`components/ui/Drawer.tsx`)

- Mobile filters, side panels
- Focus trap, Escape, scroll management

## Toast (`components/ui/Toast.tsx`)

- Noncritical success/background refresh only
- Max 5 visible; 5s auto-dismiss
- Critical failures use inline/page-level alerts

## Tooltip (`components/ui/Tooltip.tsx`)

- Supplementary info only; keyboard/focus support

## Migrated

- `FareChangeDialog` → shared Dialog
- `MobileFilterDrawer` → shared Drawer
