# JP-UI Mockup Inventory and Source of Truth

Phase: **JP-UI-01**  
Baseline: `5fad262`  
Canonical mockup storage: `C:\Users\khadi\Backup Safe` (read-only; untouched by this phase)

## Authority rules

| Source | Authoritative for |
|--------|-------------------|
| Mockups | Layout, hierarchy, spacing, component placement, geometry, theme intent, animation intent |
| Laravel / CMS / public config | Content, data, permissions, validation, actions, operational state |
| Next.js | Rendering Laravel-authoritative state using the approved visual system |

Mockups are **not** authoritative for literal PNRs, prices, routes, contact details, unsupported providers, or illustrative controls.

## Canonical mockup manifest

All mockups: **1122×1402 px**, desktop-only (no dedicated mobile counterpart). Status: **canonical desktop reference**; mobile reflow is implied.

| # | Page mapping | Filename | Absolute path | Size (bytes) | SHA-256 |
|---|--------------|----------|---------------|--------------|---------|
| 1 | Homepage | `ChatGPT Image Jul 27, 2026, 05_14_42 PM (1).png` | `C:\Users\khadi\Backup Safe\ChatGPT Image Jul 27, 2026, 05_14_42 PM (1).png` | 1,639,724 | `99BF12F5CC4590ECF49818A4D4C1A1E11C9B6F9852CE2B4F9A11125CBAB93837` |
| 2 | About JetPakistan | `ChatGPT Image Jul 27, 2026, 05_14_44 PM (2).png` | `C:\Users\khadi\Backup Safe\ChatGPT Image Jul 27, 2026, 05_14_44 PM (2).png` | 1,441,809 | `A2FEBCBDBA6A1A9DB77CDB2D65B6DF31E0EB0A9C112A4D90E0FAB00300576542` |
| 3 | Public Support / Help Center | `ChatGPT Image Jul 27, 2026, 05_14_45 PM (3).png` | `C:\Users\khadi\Backup Safe\ChatGPT Image Jul 27, 2026, 05_14_45 PM (3).png` | 1,351,791 | `9DF6CFE377A821C0D89197297C7306F8702FA337205DDF2CB8C3EF1800D094F6` |
| 4 | Passenger / traveler information | `ChatGPT Image Jul 27, 2026, 05_14_46 PM (4).png` | `C:\Users\khadi\Backup Safe\ChatGPT Image Jul 27, 2026, 05_14_46 PM (4).png` | 1,206,176 | `CB1010636C4E465B0A2BBDA5C9B7E3F379C515EBF8785569393C12BD4FB74006` |
| 5 | Booking success / confirmation | `ChatGPT Image Jul 27, 2026, 05_14_46 PM (5).png` | `C:\Users\khadi\Backup Safe\ChatGPT Image Jul 27, 2026, 05_14_46 PM (5).png` | 1,580,732 | `B236A3019827C8FF29C7D3920C60D0B3035BFC826009E780EF8B1F0CC61E7FA8` |
| 6 | Login | `542ee36d-c542-4eec-b5d4-995d555f8ba6.png` | `C:\Users\khadi\Backup Safe\542ee36d-c542-4eec-b5d4-995d555f8ba6.png` | 1,413,347 | `5CE005169FD6F882202FCDA231A50D4C5EFCD0F7F53E33875F5050DEF84AE21C` |
| 7 | Sign up / registration | `0896e3e1-8c0f-45f2-a3ac-561cd50e3f7a.png` | `C:\Users\khadi\Backup Safe\0896e3e1-8c0f-45f2-a3ac-561cd50e3f7a.png` | 1,509,414 | `257A76F10EB6C0953D32A521D5E6C706F01F3A9E25D1692E93267D357296A181` |
| 8 | Review and confirm booking | `64460b63-9930-478c-96cb-e7a00345caea.png` | `C:\Users\khadi\Backup Safe\64460b63-9930-478c-96cb-e7a00345caea.png` | 1,296,958 | `05585C5AF6C414D16F07CCA6BFDFCFA653D019431F2C45EF002395EEFA891848` |
| 9 | Manage booking / guest lookup | `678318b0-28f6-4588-ad03-f405f361152e.png` | `C:\Users\khadi\Backup Safe\678318b0-28f6-4588-ad03-f405f361152e.png` | 1,640,026 | `922C631067F7818D1C0BDF2627746C3E77F16239365E8F8B90290DAC6CEE3545` |
| 10 | Payment | `ab903350-d59f-4b60-b254-9350e4da8f00.png` | `C:\Users\khadi\Backup Safe\ab903350-d59f-4b60-b254-9350e4da8f00.png` | 1,193,833 | `C235D9038DFF7D1DD3C0E0CFB2046E493972A7E7EE44C0248D259C7E9D2A59F9` |
| 11 | Flight details / fare selection | `6ea78679-e345-49ea-a4be-2e2f539940c6.png` | `C:\Users\khadi\Backup Safe\6ea78679-e345-49ea-a4be-2e2f539940c6.png` | 1,576,904 | `6786EFB60EDE43225441CE78EAF182ABDB6F7FD6C8C485E5D4D7DBBAF4BCDE72` |
| 12 | Seat selection | `45f39a0b-e38f-4ad2-9077-f631217bd185.png` | `C:\Users\khadi\Backup Safe\45f39a0b-e38f-4ad2-9077-f631217bd185.png` | 1,421,245 | `C5B2AF6314135BC0B83E2E9E63B92E402EA34D2D207904DBD2319D4AEEDD63A2` |
| 13 | Flight search results | `520bfb29-bc9c-432c-88f1-b53cdadb1592.png` | `C:\Users\khadi\Backup Safe\520bfb29-bc9c-432c-88f1-b53cdadb1592.png` | 1,554,582 | `BB32B0FC41197A174A5E23F4C27AAB0A8D251F7C4BCED859ED30446C61DFB8BB` |

**Classification:** All 13 are **canonical desktop** references. **Missing mobile counterpart** for every page. Seat selection is **conditional** (visual target only when Laravel confirms seat-map capability).

## Per-mockup visual inventory (summary)

### 1 — Homepage
- **Header:** Logo left; nav center (Flights, Hotels, Groups, Offers, Travel Services, Support); currency + login + Book Now right.
- **Hero:** Full-width photographic aircraft/city; title left; **compact single-row search card** overlapping hero.
- **Below hero:** Benefit strip, dotted flight-path scroll indicator, destination carousel, featured offers, Why JetPakistan, support banner, travel inspiration, footer.
- **Progress steps:** N/A.
- **Animations:** Scroll indicator path, carousel motion, hero layering.

### 2 — About
- Hero with mission copy, animated flight-path area, value cards, journey timeline, metrics, benefits, CTA, footer.

### 3 — Support
- Hero + help search, category cards, FAQ accordion, contact cards, ticket CTA, emergency-support presentation, agent illustration, benefit strip, footer.

### 4 — Passengers
- **5-step progress:** Search → Results → Travelers → Seats (optional) → Review → Payment → Success family.
- Two-column: passenger forms left; sticky order summary right.
- Contact block, policy notes.

### 5 — Success
- Celebration/success hero, reference cards, itinerary, passenger summary, actions, support module.

### 6 — Login
- Split screen: form card left; illustration right. Social login row (conditional). OTP path implied.

### 7 — Sign up
- Split screen; role/account type selection in mockup (must map to real Laravel account types only).

### 8 — Review
- Shared booking shell; progress stepper; flight summary; passenger list; price breakdown; payment method selector; terms; sticky summary.

### 9 — Manage booking
- Hero; PNR/last-name lookup card; booking actions; support and security cards; benefit strip.

### 10 — Payment
- Progress on Payment step; method panels; amount due; instructions; order summary sidebar.

### 11 — Fare selection
- Dedicated fare-family layout with segment detail, branded fare cards, baggage row, Select Fare CTA. **Note:** Current app implements fare families inline on results, not a separate route.

### 12 — Seat selection
- Aircraft seat map, traveler-seat mapping, legend, pricing sidebar. **Conditional** — backend `seat_map_available: false`.

### 13 — Results
- Compact edit-search bar; result count; filters left; sort tabs; dense result cards with branded fare sub-cards; mobile filter drawer implied.

## Controls requiring operational verification (illustrative in mockups)

Hotels nav, Offers nav, Travel Services, newsletter subscribe, social login providers beyond configured OAuth, emergency hotline claims, live flight status, Change Flight / Add Baggage on lookup, card entry UI (AbhiPay handoff), WhatsApp itinerary, unsupported payment methods, mockup-specific account roles (Family Manager, Business Traveler).

## Runtime asset policy

- Do **not** copy mockup PNGs into `public/` or application assets.
- Do **not** use mockups as full-page backgrounds.
- Preserve **image slot geometry** with CMS/configurable production assets in later phases.
