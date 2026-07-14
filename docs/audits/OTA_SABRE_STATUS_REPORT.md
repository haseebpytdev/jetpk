# OTA Sabre Status Report

Generated: 2026-06-29T16:27:59+00:00

Classification: **READ-ONLY** (no supplier mutation)
live_supplier_call_attempted=false

## Module flags

| Flag | Value | Classification |
|------|-------|----------------|
| sabre_gds | on | safe |
| sabre_ndc | on | safe |
| booking_enabled (config) | true | needs manual verification |
| ticketing_enabled (config) | false | needs manual verification |

## Warnings

- Sabre connections exist but none are marked active.

## Primary active connection

- None configured

## Config flags

- booking_enabled: enabled
- ticketing_enabled: disabled
- cancel_enabled: disabled
- cancel_live_call_enabled: disabled
- booking_live_call_enabled: enabled
- verified_multiseg_auto_pnr_enabled: disabled
- cpnr_connecting_same_carrier_gds_enabled: disabled
- cpnr_connecting_same_carrier_public_checkout_enabled: disabled

## Provider mutation policy

| Capability | Status | Live call | Production |
|------------|--------|-----------|------------|
| GDS PNR creation | enabled | no | no |
| GDS PNR retrieve / sync | enabled | yes | yes |
| GDS ticketing | enabled | no | no |
| GDS cancellation | enabled | no | no |

## Retrieve/sync route readiness

- admin.bookings.sync-pnr-itinerary: registered
- staff.bookings.sync-pnr-itinerary: registered

## Connections (1)

- #2 Agency #1: **Sabre** — active=no, status=inactive, env=sandbox, host=api-crt.cert.havail.sabre.com, gds=on, ndc=off, auth=no (safe — no credentials)

## Recent failures (0)

