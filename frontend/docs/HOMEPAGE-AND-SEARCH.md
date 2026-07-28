# Homepage & Search — Frontend Architecture

Phase **JP-FE-02** owns the public homepage at `/` inside `frontend/app/page.tsx`.

## Route ownership

| Route | File | Notes |
| --- | --- | --- |
| `/` | `frontend/app/page.tsx` | Server component; wraps `HomepageContent` in `PublicShell` |

Homepage presentation sections live under `frontend/features/home/`. Interactive search lives under `frontend/features/search/`.

## Search draft contract

Client-side search builds typed drafts only — no supplier calls.

### `SearchDraft` (flights)

```ts
type SearchDraft = {
  mode: "one_way" | "return" | "multi_city";
  segments: FlightSegment[];
  passengers: PassengerSelection;
  options: SearchOptions;
  submittedAt: string;
};
```

### `GroupSearchDraft`

```ts
type GroupSearchDraft = {
  origin: Airport | null;
  destination: string;
  category: string;
  travelDate: string;
  passengers: PassengerSelection;
  submittedAt: string;
};
```

On submit, the UI validates locally and shows an integration preview message. In development, the draft JSON is logged/rendered for inspection.

## Fixture → Laravel replacement plan

| Fixture module | Service boundary | Future Laravel source |
| --- | --- | --- |
| `features/search/fixtures/airports.ts` | `services/airports.ts` (`AirportSearchService`) | `GET /airports/search?q=` |
| `features/home/fixtures/*.ts` | `services/homepage-content.ts` (`HomepageContentService`) | CMS / public homepage API |
| Search submit | N/A (preview only) | Flight search controller + group ticketing routes |

Replace fixtures by implementing the service methods to call Laravel and mapping responses to the existing TypeScript types. Do not duplicate fare logic in Next.js.

## Multi-city presentation limit

`MULTI_CITY_MAX_SEGMENTS = 6` (minimum 2) is a frontend-only cap until the Laravel search contract is connected.

## Assets

Repository-owned placeholders live under `frontend/public/images/home/`.
