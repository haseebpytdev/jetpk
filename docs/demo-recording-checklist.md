<<<<<<< HEAD
# Demo video recording checklist (Asif Travels OTA)

Use this as a shot list when recording a screen walkthrough (OBS, Xbox Game Bar, or similar). Duration target: 8–15 minutes.
=======
﻿# Demo Video Recording Checklist — JetPakistan OTA

Use this as a shot list when recording a screen walkthrough (OBS, Xbox Game Bar, or similar). Duration target: 8â€“15 minutes.
>>>>>>> jetpk/main

## Before recording

1. `cp database/demo.sqlite database/database.sqlite` (or copy on Windows).
2. `php artisan serve`, open `http://127.0.0.1:8000`.
3. Optional: configure Duffel test connection in Admin so live search works (token stays out of git).

<<<<<<< HEAD
## Act 1 — Public site

- **Home** — hero, flight widget (empty fields / placeholders), trust strip, fares message (PKR / no fake cards).
- **Flights search** — dedicated search page; trip tabs (one-way / round-trip / multi-city) if shown.
- Run a **valid search** (future date, IATA codes via autocomplete) → **results** — filters sidebar (desktop), policy line (PKR, past dates, 10h rule), fare cards (Rs … PKR, Book Now vs not bookable).
- **Load more** / filter change → list updates (AJAX).

## Act 2 — Booking path

- **Book Now** on a bookable fare → **passenger details** → **review** → **confirmation** (as far as stack allows without payment).

## Act 3 — Auth & portals (seed users)

- **Customer login** → customer dashboard (if applicable).
- **Agent login** → agent area sample.
- **Operator / admin login** → **admin** nav: bookings, API settings (show UI only—blur token fields), branding.

## Act 4 — Support & trust
=======
## Act 1 â€” Public site

- **Home** â€” hero, flight widget (empty fields / placeholders), trust strip, fares message (PKR / no fake cards).
- **Flights search** â€” dedicated search page; trip tabs (one-way / round-trip / multi-city) if shown.
- Run a **valid search** (future date, IATA codes via autocomplete) â†’ **results** â€” filters sidebar (desktop), policy line (PKR, past dates, 10h rule), fare cards (Rs â€¦ PKR, Book Now vs not bookable).
- **Load more** / filter change â†’ list updates (AJAX).

## Act 2 â€” Booking path

- **Book Now** on a bookable fare â†’ **passenger details** â†’ **review** â†’ **confirmation** (as far as stack allows without payment).

## Act 3 â€” Auth & portals (seed users)

- **Customer login** â†’ customer dashboard (if applicable).
- **Agent login** â†’ agent area sample.
- **Operator / admin login** â†’ **admin** nav: bookings, API settings (show UI onlyâ€”blur token fields), branding.

## Act 4 â€” Support & trust
>>>>>>> jetpk/main

- **Support**, **Contact**, **Lookup booking** (guest).

## Closing line (voiceover)

<<<<<<< HEAD
> “Fares display in PKR; past dates and same-day departures inside ten hours are blocked; Book Now only appears when the fare is confirmed bookable in PKR.”
=======
> â€œFares display in PKR; past dates and same-day departures inside ten hours are blocked; Book Now only appears when the fare is confirmed bookable in PKR.â€

>>>>>>> jetpk/main
