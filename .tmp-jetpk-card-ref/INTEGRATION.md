# JetPakistan — Flight Result Card Kit

Compact, aligned result cards for the flight search page, with inline **branded fares**, a **Flight Details** modal, and a **Fare Summary** modal (Baggage / Fare Policy / Fare Details). Built to match the existing JetPakistan theme (orange primary, teal secondary, slate surfaces, mono micro-labels).

> **For the AI assistant (Cursor / ChatGPT) reading this:** This is a self-contained UI kit. Do **not** invent new markup or class names — reuse the exact structure and classes documented here. The three files (`flight-cards.css`, `flight-cards.js`, and the markup below) are the source of truth. Your job is to (1) drop the assets in, (2) convert the static card markup into a Blade partial driven by the app's search results, and (3) wire the `Select` actions to the real booking route. Nothing else needs to change.

---

## 1. What's in the kit

| File | Purpose |
|---|---|
| `flight-cards.css` | All component styles, namespaced under `.jp-`. Theme tokens are CSS variables on `:root`. |
| `flight-cards.js` | Vanilla controller (no dependencies): fare-tray toggle, one shared modal, tabs, a11y. |
| `preview.html` | Standalone demo (assets inlined). Open in a browser to see the target behaviour. Reference only — do not ship. |
| `INTEGRATION.md` | This document. |

**Nothing depends on jQuery, Bootstrap, Tailwind, or a build step.** It works dropped into a plain Blade view. If the project uses Vite/Tailwind, section 9 covers that.

---

## 2. Install

```
public/
  css/flight-cards.css
  js/flight-cards.js
```

In the flights results layout `<head>`:

```blade
{{-- fonts: display + mono give the "boarding pass" feel; swap if the app already loads these --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="{{ asset('css/flight-cards.css') }}">
```

Before `</body>`:

```blade
<script src="{{ asset('js/flight-cards.js') }}"></script>
<script>document.addEventListener('DOMContentLoaded', () => JPFlights.init());</script>
```

**Scope class:** put `class="jp-scope"` on the results `<body>` (or any wrapper that contains the cards). Styles are scoped to `.jp-scope` so they never leak into the rest of the app.

---

## 3. Theme tokens

Retune the whole system from `:root` in `flight-cards.css`. Map these to the app's existing variables if they exist; otherwise leave as-is (they already match the screenshots).

| Token | Value | Used for |
|---|---|---|
| `--jp-orange` / `--jp-orange-600` | `#F26F21` / `#DE5E12` | Primary buttons, stop badges, focus ring |
| `--jp-teal` / `--jp-teal-700` | `#0E9A8A` / `#0B7A6E` | Links, "direct" badge, refundable/included status |
| `--jp-ink` | `#0F1B2D` | Airport codes, times, prices, headings |
| `--jp-slate` / `--jp-muted` | `#46566B` / `#8194AB` | Body / captions |
| `--jp-line` / `--jp-bg` / `--jp-surface` | `#E7ECF3` / `#EEF3F8` / `#F6F9FC` | Borders / page bg / subtle fills |
| `--jp-r` / `--jp-r-lg` | `13px` / `18px` | Card + control radii |

Fonts: `--jp-font-disp` (Space Grotesk — codes/prices), `--jp-font` (Inter — UI text), `--jp-font-mono` (JetBrains Mono — labels/source tags).

---

## 4. The data contract (`data-flight`)

Every card carries one JSON object on `data-flight`. The JS reads it to build both modals, so **the modals need no separate markup per flight** — one shared modal serves the whole page. This is what keeps a 50-result page cheap.

```jsonc
{
  "airline":       { "name": "PIA", "code": "PK" },   // code = IATA (used for logo lookup)
  "flightNumber":  "PK 203",
  "cabin":         "Economy",
  "stops":         "Direct",                            // "Direct" | "1 Stop" | "2 Stops"
  "duration":      "3h 55m",
  "departure":     { "code": "LHE", "city": "Lahore", "time": "12:35", "date": "Fri, 31 Jul" },
  "arrival":       { "code": "DXB", "city": "Dubai",  "time": "16:30", "date": "Fri, 31 Jul" },
  "source":        "Sabre GDS",                         // shown as the mono source tag
  "currency":      "Rs",                                // for the "from" price on the card
  "priceFrom":     "78,796",

  // OPTIONAL — only for connecting itineraries. If omitted, the Flight Details
  // modal renders a single leg from departure/arrival above.
  "segments": [
    { "airlineName":"Etihad Airways","airlineCode":"EY","flightNo":"329","cabin":"Y",
      "duration":"2h 55m",
      "dep":{"code":"LHE","city":"Lahore","time":"04:05","date":"Fri, 31 Jul"},
      "arr":{"code":"AUH","city":"Abu Dhabi","time":"06:00","date":"Fri, 31 Jul"} },
    { "airlineName":"Etihad Airways","airlineCode":"EY","flightNo":"381","cabin":"Y",
      "duration":"1h 20m","layover":"3h 40m",
      "dep":{"code":"AUH","city":"Abu Dhabi","time":"09:40","date":"Fri, 31 Jul"},
      "arr":{"code":"DXB","city":"Dubai","time":"11:00","date":"Fri, 31 Jul"} }
  ],

  // Branded fares — one card each in the tray, and each has a Fare Summary modal.
  "fares": [
    { "name":"ECOLIGHT", "tag":"Cheapest",              // tag: "Cheapest" | "Flexible" | "Recommended" | null
      "carryOn":"Airline policy", "checkIn":"Not included",
      "meal":"Not specified", "refund":"Refundable",
      "changes":"As per airline rules",                 // optional, shown in Fare Policy tab
      "currency":"PKR", "price":"78,796", "pax":1,
      "breakdown": { "base":"44,439", "taxes":"34,357", "total":"78,796" } }
    // ... more fares
  ]
}
```

**Formatting rule:** numbers are pre-formatted strings (`"78,796"`), not integers — the UI prints them verbatim. Do the number_format + currency in PHP so the front end stays dumb. Value colours are auto-derived: strings containing `not` / `—` / `0` render muted; `refundable` / `included` render teal.

---

## 5. Result card markup (annotated skeleton)

This is the exact structure. In Blade you'll loop it; the annotations map each block to the data.

```html
<article class="jp-flight" data-flight="{{-- @json($flight) --}}">

  <!-- badge: --direct (teal) or --stops (orange). Absolutely positioned top-right. -->
  <span class="jp-badge jp-badge--direct jp-flight__badge">Direct</span>

  <!-- SUMMARY ROW : airline | route | price -->
  <div class="jp-flight__main">

    <!-- airline: monogram tile OR real <img>. data-mono = coloured letter fallback. -->
    <div class="jp-flight__airline">
      <span class="jp-airline-logo" data-mono style="background:#0A5C3E">PK</span>
      <span class="jp-airline-meta">
        <span class="jp-airline-name">PIA</span>
        <span class="jp-airline-sub">Economy · PK 203</span>
      </span>
    </div>

    <!-- route : the signature boarding-pass strip -->
    <div class="jp-route">
      <div class="jp-route__end jp-route__end--dep">
        <span class="jp-time">12:35</span><span class="jp-code">LHE</span><span class="jp-place">Lahore</span>
      </div>
      <div class="jp-route__path">
        <span class="jp-route__dur">3h 55m</span>
        <span class="jp-route__line">
          <!-- for a stop, add: <span class="jp-route__pip" style="left:50%"></span> and move plane left:78% -->
          <span class="jp-route__plane" data-icon="plane"></span>
        </span>
        <span class="jp-route__stops jp-route__stops--direct">Direct</span>
      </div>
      <div class="jp-route__end jp-route__end--arr">
        <span class="jp-time">16:30</span><span class="jp-code">DXB</span><span class="jp-place">Dubai</span>
      </div>
    </div>

    <!-- price + primary CTA. data-fare-toggle opens the fare tray. -->
    <div class="jp-flight__price">
      <span class="jp-price__label">from</span>
      <span class="jp-price__amount"><span class="jp-price__cur">Rs</span>78,796</span>
      <span class="jp-price__per">per person</span>
      <button class="jp-btn jp-btn--primary" data-fare-toggle>Select fare</button>
    </div>
  </div>

  <!-- META FOOTER : chips + toggle links -->
  <div class="jp-flight__foot">
    <div class="jp-flight__tags">
      <span class="jp-chip"><span data-icon="cabin"></span> Cabin bag only</span>
      <span class="jp-chip jp-chip--ok"><span data-icon="refund"></span> Refundable</span>
      <span class="jp-src">Sabre GDS</span>
    </div>
    <div class="jp-flight__links">
      <button class="jp-link" data-modal="flight-details">Flight details</button>
      <span class="jp-dot">·</span>
      <button class="jp-link jp-link--toggle" data-fare-toggle aria-expanded="false">
        Fare options <span class="jp-caret" data-icon="caret"></span>
      </button>
    </div>
  </div>

  <!-- BRANDED FARE TRAY : collapsed by default, animates open. -->
  <div class="jp-fares"><div class="jp-fares__inner"><div class="jp-fares__pad">
    <div class="jp-fares__head">Select a fare option</div>
    <div class="jp-fares__grid">

      <!-- one .jp-fare per fare. Add jp-fare--recommended for the highlighted ring. -->
      <div class="jp-fare">
        <div class="jp-fare__top">
          <span class="jp-fare__name">ECOLIGHT</span>
          <span class="jp-fare__tag jp-fare__tag--cheap">Cheapest</span> {{-- --cheap | --flex | --best --}}
        </div>
        <ul class="jp-fare__list">
          <li class="jp-fare__row"><span data-icon="cabin"></span>  <span class="jp-fare__k">Carry-on</span><span class="jp-fare__v">Airline policy</span></li>
          <li class="jp-fare__row"><span data-icon="checked"></span><span class="jp-fare__k">Checked</span> <span class="jp-fare__v jp-fare__v--off">Not included</span></li>
          <li class="jp-fare__row"><span data-icon="meal"></span>   <span class="jp-fare__k">Meal</span>    <span class="jp-fare__v jp-fare__v--off">—</span></li>
          <li class="jp-fare__row"><span data-icon="refund"></span> <span class="jp-fare__k">Refund</span>  <span class="jp-fare__v jp-fare__v--ok">Refundable</span></li>
        </ul>
        <div class="jp-fare__foot">
          <span class="jp-fare__price"><small>PKR</small>78,796</span>
          <div class="jp-fare__actions">
            <!-- opens Fare Summary for THIS fare (index into fares[]) -->
            <button class="jp-btn jp-btn--ghost jp-btn--sm" data-modal="fare-summary" data-fare="0">Details</button>
            <!-- wire this to booking (see §7) -->
            <button class="jp-btn jp-btn--primary jp-btn--sm">Select</button>
          </div>
        </div>
      </div>

    </div>
  </div></div></div>
</article>
```

**Value modifiers** on `.jp-fare__v`: append `--ok` (teal, e.g. included / refundable) or `--off` (grey, e.g. not included / non-refundable). Icons (`cabin`, `checked`, `meal`, `refund`, `plane`, `caret`, `close`) are injected by JS into any `[data-icon]` span, so the markup stays icon-free.

---

## 6. The shared modal (include once per page)

Put this **once**, just before `</body>`. The JS finds it by `#jpModal` and fills it on open. Do not duplicate per card.

```html
<div class="jp-modal" id="jpModal" hidden>
  <div class="jp-modal__backdrop" data-modal-close></div>
  <div class="jp-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="jpModalTitle" tabindex="-1">
    <div class="jp-modal__head">
      <div>
        <h2 class="jp-modal__title" id="jpModalTitle" data-modal-title></h2>
        <p class="jp-modal__sub" data-modal-sub></p>
      </div>
      <button class="jp-modal__x" data-modal-close aria-label="Close"><span data-icon="close"></span></button>
    </div>
    <div class="jp-modal__body" data-modal-body></div>
    <div class="jp-modal__foot" data-modal-foot></div>
  </div>
</div>
```

Triggers, anywhere inside a `.jp-flight`:
- `data-modal="flight-details"` → segments/layovers view (built from `segments`, or a single leg).
- `data-modal="fare-summary" data-fare="<i>"` → Baggage / Fare Policy / Fare Details tabs for `fares[i]`, with the price-breakdown table and grand total.

---

## 7. Wiring the real actions

The demo `Select` buttons are inert. Wire them one of two ways.

**A. Links (simplest for a Laravel POST-to-booking flow).** Turn each fare `Select` into a form button carrying the fare id:

```blade
<form action="{{ route('booking.hold') }}" method="POST" class="jp-fare__actions">
  @csrf
  <input type="hidden" name="offer_id" value="{{ $flight['id'] }}">
  <input type="hidden" name="fare_id"  value="{{ $fare['id'] }}">
  <button class="jp-btn jp-btn--ghost jp-btn--sm" type="button" data-modal="fare-summary" data-fare="{{ $i }}">Details</button>
  <button class="jp-btn jp-btn--primary jp-btn--sm" type="submit">Select</button>
</form>
```

**B. JS event (for an SPA-ish flow).** The modal's `Select fare` button dispatches a `jp:selectFare` event. Listen and route:

```js
document.addEventListener('jp:selectFare', (e) => {
  const card = document.querySelector('#jpModal');           // or track the active card
  // e.detail.fareIndex -> the chosen fare
  // proceed to booking, e.g. window.location = `/book/${offerId}/${fareIndex}`;
});
```

For a multi-result page, capture the active flight id when a `data-modal` trigger is clicked (delegate on `.jp-flight`) so `jp:selectFare` knows which offer it belongs to.

---

## 8. JS API

| Call | Effect |
|---|---|
| `JPFlights.init()` | Bind everything. Call once after DOM ready. |
| `JPFlights.open({title, sub, body, foot})` | Open the shared modal with custom HTML (rarely needed). |
| `JPFlights.close()` | Close it. |
| `JPFlights.hydrateIcons(root)` | Inject SVGs into `[data-icon]` spans under `root`. **Call this after injecting cards via AJAX.** |
| event `jp:selectFare` | Fired on `document` when a modal `Select fare` is pressed; `detail.fareIndex`. |

**AJAX / infinite scroll:** after appending new `.jp-flight` nodes, call `JPFlights.hydrateIcons(newContainer)`. Click handling is delegated on `document`, so toggles and modals work on new cards automatically — no re-init needed.

---

## 9. Laravel structure (recommended)

```
resources/views/flights/
  results.blade.php                 # the page: layout + @foreach + modal include
  partials/
    result-card.blade.php           # ONE card, receives $flight (array from §4)
    modal.blade.php                 # the shared #jpModal markup from §6
app/Support/
  FlightCardPresenter.php           # maps a raw GDS/search result -> the §4 array
```

**results.blade.php**

```blade
<div class="jp-scope">
  <section class="results">
    @foreach ($flights as $flight)
      @include('flights.partials.result-card', ['flight' => $flight])
    @endforeach
  </section>
  @include('flights.partials.modal')
</div>
```

**partials/result-card.blade.php** — start from the §5 skeleton, then:

- Put the JSON on the root safely with the `@json` directive (it hex-escapes quotes **and** apostrophes, so it's attribute-safe):

  ```blade
  <article class="jp-flight" data-flight="@json($flight)">
  ```

- Drive the badge / stop pip from `stops`:

  ```blade
  @php $direct = \Illuminate\Support\Str::startsWith($flight['stops'], 'Direct'); @endphp
  <span class="jp-badge {{ $direct ? 'jp-badge--direct' : 'jp-badge--stops' }} jp-flight__badge">
    {{ $flight['stops'] }}
  </span>
  ...
  <span class="jp-route__line">
    @unless($direct)<span class="jp-route__pip" style="left:50%"></span>@endunless
    <span class="jp-route__plane" data-icon="plane" @unless($direct)style="left:78%"@endunless></span>
  </span>
  <span class="jp-route__stops {{ $direct ? 'jp-route__stops--direct' : 'jp-route__stops--stops' }}">
    {{ $flight['stops'] }}{{ $direct ? '' : ' · '.$flight['viaCode'] }}
  </span>
  ```

- Loop fares for the tray:

  ```blade
  <div class="jp-fares__grid">
    @foreach ($flight['fares'] as $i => $fare)
      <div class="jp-fare {{ ($fare['tag'] ?? '') === 'Recommended' ? 'jp-fare--recommended' : '' }}">
        <div class="jp-fare__top">
          <span class="jp-fare__name">{{ $fare['name'] }}</span>
          @if(!empty($fare['tag']))
            <span class="jp-fare__tag {{ [
                'Cheapest'   => 'jp-fare__tag--cheap',
                'Flexible'   => 'jp-fare__tag--flex',
                'Recommended'=> 'jp-fare__tag--best',
            ][$fare['tag']] ?? 'jp-fare__tag--cheap' }}">{{ $fare['tag'] }}</span>
          @endif
        </div>
        <ul class="jp-fare__list">
          <li class="jp-fare__row"><span data-icon="cabin"></span><span class="jp-fare__k">Carry-on</span>
              <span class="jp-fare__v">{{ $fare['carryOn'] }}</span></li>
          <li class="jp-fare__row"><span data-icon="checked"></span><span class="jp-fare__k">Checked</span>
              <span class="jp-fare__v {{ \Str::contains(strtolower($fare['checkIn']), ['not','0','—']) ? 'jp-fare__v--off' : 'jp-fare__v--ok' }}">{{ $fare['checkIn'] }}</span></li>
          <li class="jp-fare__row"><span data-icon="meal"></span><span class="jp-fare__k">Meal</span>
              <span class="jp-fare__v {{ \Str::contains(strtolower($fare['meal']), ['not','—']) ? 'jp-fare__v--off' : 'jp-fare__v--ok' }}">{{ $fare['meal'] }}</span></li>
          <li class="jp-fare__row"><span data-icon="refund"></span><span class="jp-fare__k">Refund</span>
              <span class="jp-fare__v {{ \Str::contains(strtolower($fare['refund']), 'refundable') && !\Str::contains(strtolower($fare['refund']),'non') ? 'jp-fare__v--ok' : 'jp-fare__v--off' }}">{{ $fare['refund'] }}</span></li>
        </ul>
        <div class="jp-fare__foot">
          <span class="jp-fare__price"><small>{{ $fare['currency'] }}</small>{{ $fare['price'] }}</span>
          <div class="jp-fare__actions">
            <button class="jp-btn jp-btn--ghost jp-btn--sm" data-modal="fare-summary" data-fare="{{ $i }}">Details</button>
            <button class="jp-btn jp-btn--primary jp-btn--sm">Select</button>
          </div>
        </div>
      </div>
    @endforeach
  </div>
  ```

**app/Support/FlightCardPresenter.php** — the transformer from your GDS response to the §4 shape:

```php
<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class FlightCardPresenter
{
    /** Map one search-result offer to the flight-card data contract. */
    public static function make(array $offer): array
    {
        $segments = collect($offer['segments'] ?? []);
        $first    = $segments->first();
        $last     = $segments->last();
        $stops    = max(0, $segments->count() - 1);

        return [
            'id'           => $offer['id'],
            'airline'      => ['name' => $offer['carrier_name'], 'code' => $offer['carrier_code']],
            'flightNumber' => $offer['carrier_code'].' '.($first['flight_no'] ?? ''),
            'cabin'        => $offer['cabin'] ?? 'Economy',
            'stops'        => $stops === 0 ? 'Direct' : $stops.' Stop'.($stops > 1 ? 's' : ''),
            'viaCode'      => $stops ? ($segments[0]['arr']['code'] ?? '') : null,
            'duration'     => self::hm($offer['duration_minutes'] ?? 0),
            'departure'    => self::point($first['dep'] ?? []),
            'arrival'      => self::point($last['arr'] ?? []),
            'source'       => $offer['source'] ?? 'GDS',
            'currency'     => 'Rs',
            'priceFrom'    => number_format($offer['min_price'] ?? 0),
            'segments'     => $segments->map(fn ($s) => [
                'airlineName' => $s['carrier_name'] ?? $offer['carrier_name'],
                'airlineCode' => $s['carrier_code'] ?? $offer['carrier_code'],
                'flightNo'    => $s['flight_no'] ?? '',
                'cabin'       => $s['cabin_class'] ?? 'Y',
                'duration'    => self::hm($s['duration_minutes'] ?? 0),
                'layover'     => isset($s['layover_minutes']) ? self::hm($s['layover_minutes']) : null,
                'dep'         => self::point($s['dep']),
                'arr'         => self::point($s['arr']),
            ])->values()->all(),
            'fares'        => collect($offer['fares'] ?? [])->map(fn ($f) => [
                'name'      => strtoupper($f['brand'] ?? 'ECONOMY'),
                'tag'       => $f['tag'] ?? null,               // set 'Cheapest' on the lowest, etc.
                'carryOn'   => $f['carry_on'] ?? 'Airline policy',
                'checkIn'   => $f['checked'] ?? 'Not included',
                'meal'      => $f['meal'] ?? 'Not specified',
                'refund'    => ($f['refundable'] ?? false) ? 'Refundable' : 'Non-refundable',
                'changes'   => $f['change_rule'] ?? 'As per airline rules',
                'currency'  => 'PKR',
                'pax'       => $offer['adults'] ?? 1,
                'price'     => number_format($f['total'] ?? 0),
                'breakdown' => [
                    'base'  => number_format($f['base'] ?? 0),
                    'taxes' => number_format($f['taxes'] ?? 0),
                    'total' => number_format($f['total'] ?? 0),
                ],
            ])->values()->all(),
        ];
    }

    private static function point(array $p): array
    {
        $t = isset($p['at']) ? Carbon::parse($p['at']) : null;
        return [
            'code' => $p['code'] ?? '',
            'city' => $p['city'] ?? '',
            'time' => $t?->format('H:i') ?? ($p['time'] ?? ''),
            'date' => $t?->format('D, j M') ?? ($p['date'] ?? ''),
        ];
    }

    private static function hm(int $min): string
    {
        return intdiv($min, 60).'h '.($min % 60).'m';
    }
}
```

Controller:

```php
$flights = collect($searchResults)->map(fn ($o) => FlightCardPresenter::make($o));
return view('flights.results', compact('flights'));
```

**Vite/Tailwind projects:** move `flight-cards.css` / `flight-cards.js` into `resources/`, import them from your entry (`import '../css/flight-cards.css'`), and `@vite([...])` in the layout. The kit's classes are plain CSS and won't clash with Tailwind (all prefixed `jp-`). Keep the `.jp-scope` wrapper.

---

## 10. Airline logos

The monogram tile (`data-mono`) is a coloured-letter fallback so nothing looks broken before logos are wired. Swap for a real image:

```blade
<span class="jp-airline-logo">
  <img src="{{ 'https://your-logo-host/'.$flight['airline']['code'].'.png' }}"
       alt="{{ $flight['airline']['name'] }}"
       onerror="this.parentElement.setAttribute('data-mono','');this.parentElement.textContent='{{ $flight['airline']['code'] }}'">
</span>
```

The `onerror` falls back to the monogram automatically if a logo is missing.

---

## 11. What changed vs the old cards (rationale)

- **Compact 3-column grid** (airline · route · price) replaces the tall stacked layout — each card is roughly half the height, so more results are visible without scrolling.
- **Boarding-pass route strip**: mono times + IATA codes + a single dashed path with the plane. Direct vs. stop is one glance (teal line-dots vs. an orange layover pip), instead of a separate "Direct" line of text.
- **Labelled meta chips** ("Cabin bag only", "23 kg included", "Refundable") replace the ambiguous bare "0 kg".
- **Fare tray is attached**, animating open *inside* the card border, instead of the old detached white panel that overlapped the card edge.
- **Consistent alignment**: one right-aligned price column, one primary orange CTA per surface, dashed dividers, soft 18px radii, 1px hairline borders + a light shadow — the "rough borders" go away.
- **One shared modal** for the whole page keeps the DOM light regardless of result count.

---

## 12. Accessibility & behaviour (already built in)

- Modal: `role="dialog"`, `aria-modal`, labelled title, **Esc to close**, backdrop-click to close, focus trap, focus returns to the trigger.
- Fare toggle exposes `aria-expanded`; tabs use `role="tablist/tab/tabpanel"` with `aria-selected`.
- Visible keyboard focus ring (orange), and `prefers-reduced-motion` disables transitions.
- Responsive: 3-up fares → 1-up on mobile; the summary row stacks; the modal becomes a bottom sheet under 480px.

---

## 13. Integration checklist

1. [ ] Copy `flight-cards.css` + `flight-cards.js` into `public/`.
2. [ ] Add the fonts + `<link>` + `<script>` + `JPFlights.init()` to the results layout.
3. [ ] Add `class="jp-scope"` to the wrapper.
4. [ ] Create `FlightCardPresenter` (or map your existing DTO) to the §4 contract.
5. [ ] Build `result-card.blade.php` from the §5 skeleton; put `@json($flight)` on the root.
6. [ ] Include the shared `#jpModal` once (§6).
7. [ ] Wire fare `Select` buttons to the booking route (§7).
8. [ ] Swap monogram tiles for real logos (§10).
9. [ ] If AJAX-loading results, call `JPFlights.hydrateIcons(container)` after append.
