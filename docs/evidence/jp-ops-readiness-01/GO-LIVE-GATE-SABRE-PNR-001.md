# GO_LIVE_GATE_SABRE_PNR_001

**Created:** 2026-10-16  
**Linked defect:** OPS-DEF-0018  
**PROTECTED_MAIN_SHA:** `045c3fe642c5061b0cb4fb46ad7948e9613c5cf1`  
**PRODUCTION_RUNTIME_SHA:** `922f15774ece177d8b3cc23207e98379b8a2755b`

```
GO_LIVE_GATE_SABRE_PNR_001=PENDING_FUTURE_GO_LIVE_WINDOW
SABRE_LIVE_PNR_ALLOWED_NOW=NO
SABRE_LIVE_PNR_REQUIRED_BEFORE_ACTUAL_GO_LIVE=YES
LIVE_PNR_DEFERRAL_REASON=Supplier financial/fine/ADM exposure before production launch window
```

## Purpose

Final production gate for **one** controlled Sabre GDS CreatePNR UAT immediately before actual go-live. Technical readiness is documented; **live supplier mutation is not authorized now**.

## Required checklist (execute only with explicit go-live authorization)

1. Explicit user authorization for live Sabre CreatePNR  
2. Approved real QA traveler / contact identity  
3. Safe current direct one-way itinerary  
4. Supplier/fare terms reviewed (no immediate-ticket fare if avoidable)  
5. No immediate ticketing requirement; ticketing flags remain OFF / no LNIATA  
6. **One** CreatePNR only (admin-manual preferred path)  
7. No auto-ticket  
8. Controlled unticketed release/cancel if commercially safe (separate cancel auth)  
9. Verify no duplicate PNR / orphan supplier row  
10. Certify local booking ↔ supplier state  

## Hard stops

- Any CreatePNR without items 1–2  
- Public checkout PNR path for first live UAT  
- Ticketing / void / refund  
- Reuse of payment fixture `JPQAMPAY9B8E85CF`  
- Blind retry / double-submit  

## Related readiness

See `OPS-DEF-0018-SABRE-PNR-UAT-READINESS.md` (technical readiness) and `OPS-DEF-0018-PRICING-READINESS.md` (deferral + pricing).
