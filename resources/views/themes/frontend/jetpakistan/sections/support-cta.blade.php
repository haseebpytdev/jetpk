@php
    use App\Support\Client\JetpkHomepageSectionData;

    $jpHome = app(JetpkHomepageSectionData::class);
    if (! $jpHome->isEnabled('support_cta')) {
        return;
    }

    $defaults = $jpHome->defaults();
    $support = $jpHome->supportCtaForDisplay();
    $eyebrow = $jpHome->field('support_cta.eyebrow', data_get($defaults, 'support_cta.eyebrow', ''));
    $title = $jpHome->field('support_cta.title', data_get($defaults, 'support_cta.title', ''));
    $subtitle = $jpHome->field('support_cta.subtitle', data_get($defaults, 'support_cta.subtitle', ''));
    $phoneLabel = $jpHome->field('support_cta.call_label', data_get($support, 'call_label', data_get($defaults, 'support_cta.phone_label', 'Call support')));
    $phoneValue = trim((string) data_get($support, 'phone_value', ''));
    $callEnabled = ($support['call_enabled'] ?? '1') === '1';
    $chatEnabled = ($support['chat_enabled'] ?? '1') === '1';
    $chatLabel = $jpHome->field('support_cta.chat_label', data_get($support, 'chat_label', data_get($defaults, 'support_cta.cta_label', 'Live chat')));
    $callUrlRaw = trim((string) data_get($support, 'call_url', ''));
    $chatUrlRaw = trim((string) data_get($support, 'chat_url', ''));
    $bgImage = $jpHome->assetUrl('support_cta_background');
    $bgMobile = $jpHome->assetUrl('support_cta_background_mobile') ?? $bgImage;
    $backgroundMode = (string) ($support['background_mode'] ?? 'gradient');
    $overlay = $jpHome->field('support_cta.overlay_strength', data_get($defaults, 'support_cta.overlay_strength', 'medium'));
    $align = $jpHome->field('support_cta.text_alignment', data_get($defaults, 'support_cta.text_alignment', 'left'));
    $useUploadedBg = $bgImage && in_array($backgroundMode, ['uploaded', 'uploaded_overlay'], true);

    $resolveActionHref = static function (string $raw): ?string {
        if ($raw === '' || $raw === '#' || str_starts_with(strtolower($raw), 'javascript:')) {
            return null;
        }

        if (str_starts_with(strtolower($raw), 'tel:')) {
            return $raw;
        }

        return str_starts_with($raw, 'http') ? $raw : client_url($raw);
    };

    $callHref = null;
    if ($callUrlRaw !== '') {
        $callHref = $resolveActionHref($callUrlRaw);
    } elseif ($phoneValue !== '') {
        $tel = preg_replace('/\s+/', '', $phoneValue);
        if ($tel !== '' && ! str_starts_with(strtolower($tel), 'javascript:')) {
            $callHref = 'tel:'.$tel;
        }
    }

    $chatHref = $chatUrlRaw !== '' ? $resolveActionHref($chatUrlRaw) : null;
@endphp
<section class="section" style="padding-top:0" data-jp-support-cta>
  <div class="wrap">
    <div class="support-cta reveal support-cta--align-{{ $align }} support-cta--overlay-{{ $overlay }} @if($useUploadedBg) support-cta--has-bg @endif support-cta--mode-{{ $backgroundMode }}"
         @if($useUploadedBg) style="--jp-support-bg: url('{{ e($bgImage) }}'); @if($bgMobile) --jp-support-bg-mobile: url('{{ e($bgMobile) }}'); @endif" @endif>
      <div class="bg"></div>
      <svg class="arc-bg" viewBox="0 0 1200 300" preserveAspectRatio="none" aria-hidden="true"><path d="M-20 240 Q600 20 1220 180"/><path d="M-20 270 Q560 60 1220 140"/></svg>
      <div class="inner">
        <div>
          @if ($eyebrow !== '')<span class="eyebrow">{{ $eyebrow }}</span>@endif
          @if ($title !== '')<h2>{{ $title }}</h2>@endif
          @if ($subtitle !== '')<p>{{ $subtitle }}</p>@endif
        </div>
        <div class="actions">
          @if ($callEnabled && $callHref)
            <a href="{{ $callHref }}" class="btn cta-call" data-jp-support-call>{{ $phoneLabel }}</a>
          @endif
          @if ($chatEnabled && $chatHref)
            <a href="{{ $chatHref }}" class="btn cta-chat" data-jp-support-chat>{{ $chatLabel }}</a>
          @endif
        </div>
      </div>
    </div>
  </div>
</section>
