# JetPakistan — Saudi MOFA Visa Lookup  
## Written authorization / policy approval request (updated after R2 technical proof)

**Status:** Draft for client / MOFA / authorized Saudi partner contact.  
**Permission received:** **NO** (this document does not claim approval).

---

## Proven technical design (no sensitive details)

Live authorized testing confirmed JetPakistan can, in principle, mediate a **user-initiated** Visa Platform inquire as follows:

1. User starts a lookup and supplies **their own** identifiers
2. JetPakistan backend opens a temporary MOFA session
3. MOFA CAPTCHA image is shown to the **same** user and solved **manually by that human**
4. Same session submits the inquire (CSRF + cookies)
5. MOFA returns a redirect to an official printable **HTML** visa page with structured fields
6. JetPakistan may display a summary from those fields and/or stream the official HTML document bytes unchanged for that user
7. No MOFA `application/pdf` download API was observed; any PDF/PNG would be a **local copy** of the official document/print view and must be labeled accordingly

---

## Commitments

| Practice | Commitment |
|---|---|
| Initiation | User-initiated only |
| Identifiers | User-owned / authorized only |
| CAPTCHA | Human-solved only; no OCR/AI/solver/bypass |
| Bulk / polling | None |
| Enumeration | None |
| Harvesting / resale | None |
| Authority | MOFA remains authoritative |
| Storage | No permanent passport/visa/document archive by default |
| Attribution | MOFA attribution shown |
| Controls | Rate limits + instant kill switch |

---

## Questions requiring written answers

1. User-initiated server-mediated Visa Platform search permitted?
2. Relaying MOFA CAPTCHA to the same user for manual solving permitted?
3. Submitting that CAPTCHA on the same temporary MOFA session permitted?
4. Displaying returned visa fields inside JetPakistan permitted?
5. Streaming the official MOFA HTML visa document to the same user permitted?
6. Temporary encrypted MOFA session-cookie handling permitted?
7. MOFA attribution on JetPakistan UI required/acceptable?
8. Does an official partner/API channel exist that **must** be used instead?

---

## Fallback

If permission is denied or unavailable: JetPakistan will only deep-link users to the official MOFA Search Visa URL — no server-mediated lookup.
