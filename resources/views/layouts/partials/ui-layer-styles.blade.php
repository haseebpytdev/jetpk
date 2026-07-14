@php
    use App\Support\Ui\UiLayerResolver;

    /** @var list<string>|null $contexts */
    $contexts = $contexts ?? ui_layer_contexts();
    $supplier = $uiLayerSupplier ?? null;
    $cssPaths = app(UiLayerResolver::class)->activeCssPaths($contexts, is_string($supplier) ? $supplier : null);
@endphp
@foreach ($cssPaths as $cssPath)
    <link rel="stylesheet" href="{{ ui_layer_asset($cssPath) }}" data-ui-layer-css="{{ $cssPath }}" />
@endforeach
