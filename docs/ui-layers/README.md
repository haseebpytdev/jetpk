# OTA Layered UI Override System

UI fixes ship as **named layer files** under `public/css/layers/` and
`public/js/layers/` instead of repeatedly editing base assets
(`ota-public.css`, `ota-admin-console.css`, etc.).

## How it works

1. Register a layer in `config/ui-layers.php` (key, contexts, order, css/js paths, rollback notes).
2. Add the layer asset file(s) under `public/css/layers/...` or `public/js/layers/...`.
3. Layouts include `layouts/partials/ui-layer-styles` and `ui-layer-scripts` **after** base CSS/JS.
4. Enable via config default, env `OTA_UI_LAYER_{KEY}`, or **Developer CP → UI Layers**.

Global kill switch: `OTA_UI_LAYERS_ENABLED=false`.

## Contexts

| Context | Typical shell |
|---------|----------------|
| `public` | Guest public pages (`layouts/frontend`) |
| `admin` | Admin console (`layouts/dashboard`, `$dashArea=admin`) |
| `staff` | Staff console |
| `agent` | Agent account shell |
| `customer` | Customer account shell |
| `flight-results` | Flight results routes (desktop + mobile) |
| `supplier-sabre` / `supplier-duffel` | Admin/staff booking UI when `$uiLayerSupplier` is set |

## Load order

Layers sort by ascending `order`, then `key`. Within a page, CSS loads after
`@stack('styles')`; JS loads after base bundle scripts.

## Supplier-specific layers

Set `$uiLayerSupplier = 'sabre'` (or `duffel`, etc.) in a Blade view before
layers render. Layers with a `suppliers` array only load when the supplier matches.

## Rollback

Each layer entry includes `rollback` notes (visible in Dev CP). Typical rollback:

1. Disable the layer key in Dev CP (or set env override to `false`).
2. Delete the layer file(s) under `public/css/layers/` or `public/js/layers/`.
3. No base asset revert required.

## Adding a new layer

```php
// config/ui-layers.php — append to `layers`
[
    'key' => 'public-header-spacing',
    'contexts' => ['public'],
    'order' => 50,
    'enabled' => true,
    'css' => ['css/layers/public/header-spacing.css'],
    'js' => [],
    'description' => 'Tighten mobile header padding.',
    'rollback' => 'Disable public-header-spacing; remove css/layers/public/header-spacing.css.',
],
```

Upload new layer CSS/JS via **OTA Public** SFTP profile. Upload config/PHP via **OTA App** profile.

## Helpers

- `ui_layer_contexts()` — detect contexts for current request
- `ui_layer_asset($path)` — asset URL with filemtime cache bust
- `ui_layer_resolver()` — `UiLayerResolver` service
