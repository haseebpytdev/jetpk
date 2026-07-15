@php
    use App\Support\Ui\UiLayerResolver;

    /** @var list<string>|null $contexts */
    $contexts = $contexts ?? ui_layer_contexts();
    $supplier = $uiLayerSupplier ?? null;
    $jsPaths = app(UiLayerResolver::class)->activeJsPaths($contexts, is_string($supplier) ? $supplier : null);
@endphp
@foreach ($jsPaths as $jsPath)
    <script src="{{ ui_layer_asset($jsPath) }}" defer data-ui-layer-js="{{ $jsPath }}"></script>
@endforeach
