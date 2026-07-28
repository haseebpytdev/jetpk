# JetPakistan Public Next.js Frontend Master Plan

**Status:** Approved and saved  
**Implementation start condition:** Begin only after the Admin/Staff Next.js dashboard is completed, validated, and integrated into the main JetPakistan repository at `C:\Users\khadi\ota-jetpk`.  
**Current public frontend policy:** Existing Laravel Blade frontend remains in maintenance mode until controlled Next.js cutover.

---

## 1. Executive Decision

JetPakistan will not undergo a broad Blade frontend refactor.

The current Laravel Blade frontend remains operational and receives only risk-based maintenance for:

- broken search, booking, checkout, payment, confirmation, account, or support flows;
- production server errors;
- exploitable security defects;
- JetPakistan branding leaks or master-client fallback links;
- severe customer-facing mobile usability failures;
- supplier/fare-state defects that block or mislead customers;
- accessibility failures that materially prevent completion of a booking.

The future public frontend will be rebuilt in Next.js using a controlled architecture, shared responsive design system, strict Laravel authority boundaries, day/night themes, and an approved motion system.

Valid findings from earlier audits are retained only as acceptance requirements for the new Next.js frontend. They are not a large Blade remediation backlog.

---

## 2. Implementation Start Gate

Do not begin the public frontend until all of the following are complete:

1. Admin/Staff Next.js dashboard is finished.
2. Dashboard visual consistency is closed at desktop, tablet, and mobile widths.
3. Dashboard authentication and server-authoritative RBAC are integrated.
4. Dashboard Laravel read-only integration is complete.
5. Dashboard branch is reviewed and integrated into the main `ota-jetpk` repository.
6. Existing Blade route map and fallback behavior are documented.
7. Public frontend architecture phase is approved.

The first public frontend phase must be architecture-first:

**JP-FE-01 — Architecture, Design System, Contracts, Route Map and CMS Registry**

Do not begin directly with homepage coding.

---

## 3. Core Architecture

### Laravel remains authoritative for

- authentication;
- authorization and RBAC;
- supplier integrations;
- search orchestration;
- offer normalization;
- cache isolation;
- stale-offer protection;
- revalidation;
- booking and PNR/order lifecycle;
- ticket/document lifecycle;
- payment state;
- cancellation eligibility and execution;
- customer and agent data;
- CMS data source;
- audit;
- all write operations.

### Next.js public frontend owns

- rendering;
- public navigation;
- responsive layouts;
- homepage and search UI;
- results presentation;
- checkout presentation;
- account presentation;
- CMS rendering;
- SEO metadata generation;
- responsive images;
- accessible client interactions;
- visual states and motion.

### Next.js must never become authoritative for

- displayed fare validity;
- supplier availability;
- booking status;
- payment status;
- ticketing status;
- cancellation status;
- PNR state;
- offer expiry;
- return-flight pairing;
- role authorization.

---

## 4. Design System

The public frontend must use one shared responsive design system across all routes.

### Required shared systems

- header;
- desktop navigation;
- mobile drawer;
- footer;
- page containers;
- breadcrumbs;
- typography;
- spacing;
- grid;
- cards;
- forms;
- buttons;
- badges;
- tabs;
- drawers;
- dialogs;
- skeletons;
- loading states;
- empty states;
- error states;
- focus-visible treatment;
- motion tokens;
- day/night theme tokens.

### Typography

Use one approved font family with a controlled fallback stack.

Define centrally:

- display heading;
- page heading;
- section heading;
- card title;
- body text;
- secondary text;
- labels;
- helper text;
- prices;
- buttons;
- navigation;
- badges;
- tables;
- checkout progress text.

No page-specific fonts or arbitrary type scales.

### Layout tokens

Centralize:

- spacing scale;
- content widths;
- page padding;
- grid gaps;
- card padding;
- border radius;
- borders;
- shadows;
- form-control heights;
- button heights;
- header heights;
- drawer widths;
- breakpoints;
- animation duration;
- easing;
- motion distance;
- reduced-motion rules.

---

## 5. Day and Night Themes

The complete public frontend must support both themes.

### Day theme

- white/off-white surfaces;
- JetPakistan green as primary accent;
- dark body text;
- soft grey borders;
- restrained shadows;
- bright, airy hero and imagery treatment.

### Night theme

- deep charcoal or dark navy surfaces;
- softened JetPakistan green accent;
- light text;
- muted green-grey borders;
- reduced-glare panels;
- theme-adjusted image overlays;
- same layout, hierarchy and spacing as day mode.

### Theme coverage

Both modes must apply to:

- homepage;
- search;
- results;
- flight details;
- checkout;
- payment;
- success page;
- About page;
- Support page;
- account pages;
- CMS/static pages;
- footer and drawers.

### Theme behavior

- header theme toggle;
- mobile drawer theme toggle;
- persisted preference;
- optional system-theme fallback;
- no flash of incorrect theme;
- no route-specific theme drift.

---

## 6. Approved Page Mockup Direction

The approved mockup direction includes:

- clean JetPakistan green/white visual identity;
- premium but restrained travel presentation;
- consistent header/footer;
- clear search-first homepage;
- adaptive card/table layouts;
- clean checkout structure;
- visible adaptive progress bar;
- success page with strong confirmation and next-step guidance;
- dedicated animation zones that never interfere with essential controls.

Pages approved for future implementation:

- final homepage;
- About page;
- Support page;
- checkout flow;
- payment step;
- success page;
- account pages;
- CMS/static pages.

---

## 7. Scroll-to-Discover Airplane Motion System

The central branded motion concept is a scroll-linked airplane journey.

The approved homepage layout remains unchanged. Motion is layered onto the existing structure.

### Core behavior

- airplane begins in the hero;
- “Scroll to Discover” cue appears near the hero bottom;
- airplane follows a restrained curved route as the user scrolls;
- route line draws progressively;
- airplane rotates slightly to align with route direction;
- selected sections reveal when the airplane reaches waypoints;
- subtle cloud, contrail and destination-pin effects may be used;
- movement remains premium and restrained, not cartoonish.

### Required component concept

- `ScrollFlightJourney`
- `AirplaneMarker`
- `FlightPath`
- `DestinationWaypoint`
- `ScrollCue`
- `SectionReveal`
- `ReducedMotionFallback`

### Motion-layer rules

The decorative motion layer must:

- use `pointer-events: none`;
- never cover search controls;
- never cover forms or buttons;
- not affect document height;
- not cause layout shift;
- not create horizontal overflow;
- remain behind or safely beside essential content;
- never be the only way to understand the page.

### Desktop motion

- fuller curved route;
- gentle airplane rotation;
- subtle contrail;
- optional cloud layers;
- section reveals;
- waypoint moments.

### Tablet motion

- shorter route;
- fewer direction changes;
- reduced decoration;
- smaller movement distances;
- no content obstruction.

### Mobile motion

- simplified vertical or edge-aligned route;
- lighter animation;
- no heavy parallax;
- no cross-screen airplane movement over forms;
- no horizontal overflow;
- no scroll-jacking;
- reduced GPU/battery impact.

### Reduced-motion behavior

When `prefers-reduced-motion` is enabled:

- airplane remains static or minimally animated;
- path may remain visible;
- sections appear immediately or use simple opacity changes;
- no parallax;
- no scroll-linked rotation;
- all content remains fully usable.

---

## 8. Exact Animation Placement

## Homepage

### H1 — Hero

- airplane idle float;
- subtle cloud drift;
- “Scroll to Discover” cue;
- animated arrow/mouse indicator;
- route path begins near hero bottom.

### H2 — Hero to Destinations transition

- airplane starts moving on scroll;
- dotted curved path draws;
- small waypoint markers appear;
- airplane angle follows route direction.

### H3 — Destinations on the Rise

- cards reveal in stagger;
- airplane slows near a waypoint;
- card hover interactions;
- no obstruction of card content.

### H4 — Featured Offers

- cards fade/slide in;
- contained carousel transitions;
- airplane may cross the section boundary only;
- price and fare text remains static and readable.

### H5 — Why JetPakistan

- feature icons/cards reveal sequentially;
- route becomes lighter and decorative;
- optional metric count-up.

### H6 — Support CTA

- airplane reaches a support waypoint;
- support block reveals;
- CTA gets restrained hover motion.

### H7 — Footer

- no scroll-linked airplane motion;
- only subtle hover states.

## About Page

### A1 — About hero

- lighter airplane route;
- animated path entry;
- no heavy scroll choreography.

### A2 — Mission and values

- staggered card reveal;
- subtle icon animation.

### A3 — Company journey timeline

- timeline line draws;
- milestones activate on entry;
- airplane route may visually connect to the history concept.

### A4 — Stats strip

- count-up when visible;
- icon fade-in.

### A5 — Final CTA

- slight airplane drift or visual motion;
- subtle CTA animation.

## Support Page

### S1 — Support hero

- airplane follows a light help-route;
- headset/help waypoint endpoint;
- search bar fades in;
- topic chips appear cleanly.

### S2 — Support topics

- staggered card reveal;
- light icon motion;
- restrained hover emphasis.

### S3 — FAQ

- accordion transitions only;
- no airplane motion over FAQ content.

### S4 — Contact cards

- fade-in;
- response-time badge emphasis;
- CTA hover motion.

### S5 — Emergency support

- minimal urgency treatment;
- no distracting animation.

## Checkout Flow

Checkout uses micro-interactions rather than a decorative airplane journey.

### C1 — Adaptive progress bar

- active step transitions;
- completed steps become checkmarks;
- line fills progressively;
- compact mobile representation.

### C2 — Traveler forms

- focus states;
- validation feedback;
- multi-traveler section expansion;
- add-traveler reveal.

### C3 — Contact information

- controlled input focus and validation;
- no decorative airplane over forms.

### C4 — Fare rules

- subtle icon/tooltip motion only.

### C5 — Payment method selection

- selected-card transition;
- radio/check animation;
- secure-payment reassurance.

### C6 — Order summary

- sticky behavior;
- safe collapse/expand on mobile;
- total-change transition;
- no decorative motion.

## Success Page

### SU1 — Final progress bar

- all steps complete;
- final node activates;
- completion line animates.

### SU2 — Success hero

- airplane hero visual;
- celebratory flight path;
- restrained confetti/particles;
- success icon scale/fade.

### SU3 — Booking reference

- reference card reveal;
- copy feedback;
- soft highlight.

### SU4 — Summary cards

- booking, passenger, itinerary and payment cards reveal in sequence.

### SU5 — What’s Next

- sequential step icons;
- gently animated arrows;
- post-booking guidance.

### SU6 — Itinerary and support CTA

- subtle device visual float;
- download/send controls;
- support CTA reveal.

---

## 9. Motion Intensity by Page

### Higher motion

- homepage;
- success page.

### Medium motion

- About page;
- Support page.

### Lower motion

- results;
- checkout;
- payment;
- account pages;
- CMS pages.

Motion must never reduce conversion, readability or performance.

---

## 10. Reusable Public Layouts

## Search layout

Used for:

- homepage flight search;
- one-way;
- return;
- multi-city;
- group search;
- nearby-origin airports;
- direct-flight filtering;
- flexible dates.

## Results layout

Shared across:

- Sabre GDS;
- Sabre NDC;
- PIA NDC;
- AirBlue/Crane;
- AirSial;
- Duffel;
- Group Ticketing;
- future suppliers.

Required shared components:

- search summary;
- modify-search panel;
- filter panel;
- sort bar;
- result card;
- segment timeline;
- layover summary;
- fare-brand selector;
- price summary;
- loading skeleton;
- empty state;
- error state.

Supplier differences must be represented through normalized capabilities, not separate visual systems.

## Checkout layout

Shared across:

- passenger details;
- contact details;
- review;
- payment;
- processing;
- booking success.

Required shared components:

- checkout shell;
- adaptive progress bar;
- passenger form;
- contact form;
- fare summary;
- price breakdown;
- policy summary;
- payment method selector;
- validation summary;
- booking status summary.

JetPakistan checkout must never fall back to Parwaaz or the master client.

Approved payment methods:

- Manual Payment;
- Pay by Card.

## Account layout

Used for:

- profile;
- bookings;
- booking detail;
- documents;
- payment status;
- support;
- agent/customer account workflows.

## CMS/static-page layout

Used for:

- About;
- Contact;
- Privacy;
- Terms;
- promotional pages;
- travel guides;
- airline content;
- future static pages.

---

## 11. Controlled CMS Renderer

CMS-created pages must not be able to break the frontend.

### Approved section registry

- hero;
- rich text;
- image and text;
- feature grid;
- benefit grid;
- statistics;
- FAQ;
- call to action;
- support callout;
- destination cards;
- promotional offers;
- notices;
- legal content;
- contact block.

Each section must have:

- strict schema;
- approved variants;
- theme-safe tokens;
- responsive behavior;
- text-length guidance;
- image rules;
- accessibility rules;
- validation.

### Prohibited CMS capabilities

- arbitrary JavaScript;
- arbitrary CSS;
- unrestricted HTML;
- raw iframes;
- inline event handlers;
- external script injection;
- custom fonts;
- arbitrary colors;
- uncontrolled widths/margins;
- absolute positioning;
- custom headers/footers;
- replacement of search or booking business logic.

### CMS motion options

Allowed:

- none;
- fade;
- fade-up;
- staggered cards;
- airplane waypoint reveal.

Not allowed:

- custom animation paths;
- arbitrary duration;
- JavaScript;
- CSS;
- z-index control;
- custom airplane coordinates;
- unrestricted parallax.

### CMS preview requirements

- desktop;
- tablet;
- mobile;
- day theme;
- night theme;
- validation warnings;
- overflow warnings;
- alt-text validation;
- unsaved local preview.

---

## 12. Accessibility Acceptance Requirements

Every phase must verify:

- semantic landmarks;
- one logical page heading;
- skip-to-content link;
- correct language attribute;
- labelled form controls;
- grouped related controls;
- keyboard navigation;
- keyboard-accessible date/passenger controls;
- visible focus states;
- focus management in drawers/dialogs;
- Escape behavior;
- focus restoration;
- meaningful image alt text;
- decorative image treatment;
- non-color status communication;
- accessible validation summaries;
- field-error associations;
- suitable touch targets;
- reduced-motion support;
- no critical hover-only information.

Accessibility must be built into shared components.

---

## 13. SEO Architecture

Centralize:

- titles;
- descriptions;
- canonical URLs;
- Open Graph;
- social images;
- robots directives;
- sitemap;
- structured data;
- no-index rules for private/account/checkout routes.

Potential structured data:

- Organization;
- WebSite;
- BreadcrumbList;
- FAQPage;
- travel-related schema only where accurate.

CMS users may provide validated metadata fields only, not unrestricted metadata payloads.

---

## 14. Performance Architecture

Require:

- responsive image handling;
- explicit image dimensions;
- controlled hero priorities;
- no layout shift;
- route-level skeletons;
- search/results skeletons;
- below-the-fold lazy loading;
- controlled third-party scripts;
- minimal client components;
- server rendering where appropriate;
- bundle-budget monitoring;
- optimized font loading;
- request cancellation;
- no stale route transition state;
- no caching that compromises offer freshness.

Motion must be GPU-conscious and reduced on mobile.

---

## 15. Hardened Laravel/Sabre Logic That Must Survive

## Search and cache isolation

- search contexts isolated by user/session/tenant/route;
- no cross-user result leakage;
- supplier/channel identity explicit;
- no stale reuse outside valid context.

## Offer expiry

- expired offers block progression;
- stale state shown clearly;
- countdown is informational;
- server remains authoritative.

## Revalidation

- selected offer revalidated before booking;
- price changes shown clearly;
- unavailable offers block checkout;
- supplier response is authoritative.

## Return pairing

- only supplier-returned or supplier-validated pairs;
- no manual outbound/return stitching;
- branded combinations remain supplier-valid.

## GDS and NDC

- Sabre GDS and Sabre NDC remain separate channels;
- shared credentials do not merge offer semantics;
- disabling GDS suppresses only the GDS lane;
- NDC does not inherit GDS PNR/LNIATA assumptions.

## Booking and checkout

- JetPakistan-only flow;
- no Parwaaz/master fallback;
- server-authoritative passenger validation;
- consistent price breakdown;
- no duplicate bookings on refresh/back navigation;
- idempotent booking and payment submissions.

## Authorization

- public/customer/agent/staff/admin authority remains in Laravel;
- frontend route visibility is presentational only;
- no client-authoritative RBAC.

---

## 16. Visual QA Gate

Every public frontend phase must include visual QA at:

- 1440px;
- 1280px;
- 1024px;
- 768px;
- 430px;
- 390px;
- 360px.

Review:

- header;
- mobile drawer;
- footer;
- containers;
- typography;
- search forms;
- result cards;
- fare selectors;
- filters;
- checkout forms;
- progress bar;
- CMS sections;
- drawers/dialogs;
- loading;
- empty;
- error;
- long airline names;
- long airport names;
- large prices;
- long passenger names;
- validation messages;
- day/night themes;
- reduced motion;
- future Urdu/RTL readiness.

A phase cannot close with unresolved P0 or P1 visual defects.

---

## 17. Proposed Public Frontend Phases

### JP-FE-01
Architecture, design system, contracts, route map, theme system, motion system and CMS registry.

### JP-FE-02
Shared shell: header, navigation, drawer, footer, containers, day/night theme and SEO framework.

### JP-FE-03
Homepage and search experience, including Scroll-to-Discover airplane journey.

### JP-FE-04
Flight results, filters, sorting, segment timeline and fare-card system.

### JP-FE-05
Offer details, selection, authoritative revalidation and stale-offer handling.

### JP-FE-06
Passenger, contact and checkout flow with adaptive progress bar.

### JP-FE-07
Payment, processing and booking-success experience.

### JP-FE-08
Customer account and booking management.

### JP-FE-09
Controlled CMS renderer and static pages, including About and Support.

### JP-FE-10
Agent-facing public/account workflows.

### JP-FE-11
Cross-route responsive, accessibility, SEO, performance and motion hardening.

### JP-FE-12
Laravel parity, controlled cutover and Blade fallback retirement.

---

## 18. Controlled Cutover Rules

Do not replace Blade routes without:

- functional parity;
- supplier parity;
- role parity;
- branding review;
- day/night theme verification;
- responsive visual QA;
- accessibility review;
- SEO review;
- performance review;
- security review;
- booking-flow regression;
- rollback verification.

Each route family must have a controlled fallback until fully approved.

---

## 19. Immediate Reminder

Current action sequence:

1. Finish Admin/Staff dashboard.
2. Integrate dashboard into main `ota-jetpk`.
3. Keep Blade frontend in maintenance mode.
4. Do not begin broad Blade refactoring.
5. Start public frontend with JP-FE-01.
6. Use this document as the authoritative design, architecture, motion and rollout reminder.

---

## 20. Approval

This JetPakistan public Next.js frontend plan is approved as the current source of truth for future implementation.

It includes:

- day/night themes;
- approved mockup direction;
- exact animation zones;
- airplane Scroll-to-Discover journey;
- adaptive checkout progress bar;
- controlled CMS renderer;
- shared responsive design system;
- accessibility, SEO and performance requirements;
- preservation of hardened Laravel/Sabre logic;
- phase-by-phase visual QA;
- controlled migration from Blade to Next.js.

Do not begin implementation until the dashboard integration gate is complete.
