# JetPakistan — Saudi MOFA Visa Lookup  
## Written authorization / policy approval request

**Status:** Draft for client / MOFA / authorized Saudi partner contact.  
**Permission received:** **NO** (this document does not grant or claim approval).

---

## 1. Purpose

JetPakistan (https://jetpakistan.pk) is evaluating an **optional** customer feature that would help travelers look up **their own** Saudi visa information using the official Ministry of Foreign Affairs Visa Platform:

`https://visa.mofa.gov.sa/visaservices/searchvisa`

We seek **written confirmation** whether this proposed user-initiated, human-CAPTCHA, server-mediated relay is permitted — or whether an official partner/API channel must be used instead.

---

## 2. What JetPakistan proposes

| Practice | Commitment |
|---|---|
| Initiation | User manually starts each lookup inside JetPakistan |
| Identity data | User supplies **their own** lookup values only |
| CAPTCHA | Official MOFA CAPTCHA image is shown to the same user and **solved by that human** |
| CAPTCHA automation | **None** — no OCR, AI, third-party solvers, or bypass |
| Bulk / polling | **No** background polling, scheduled jobs, or bulk harvesting |
| Enumeration | **No** passport/visa enumeration or guessing |
| Resale / database | **No** resale or permanent harvesting of visa records |
| Authority | MOFA remains the sole authoritative source |
| PDF | If MOFA returns an official PDF, JetPakistan would **stream the original bytes unchanged** to the same user |
| Storage | **No permanent** passport/visa/PDF archive by default; only short-lived encrypted session handling |
| Attribution | UI would state that visa information is supplied by the Saudi Ministry of Foreign Affairs |
| Rate limits | JetPakistan would honor MOFA throttling / fail closed |
| Kill switch | Feature can be disabled instantly without core OTA impact |

---

## 3. Explicit questions requiring written answers

Please confirm in writing whether MOFA / an authorized Saudi partner **permits**:

1. User-initiated **server-mediated** Visa Platform search (JetPakistan backend ↔ MOFA, on behalf of the same end user);
2. Relaying the MOFA CAPTCHA image to that same end user for **manual** solving;
3. Submitting the user-entered CAPTCHA through the **same temporary MOFA session**;
4. Displaying returned visa information inside the JetPakistan UI (with MOFA attribution);
5. Proxying/streaming the **original MOFA-issued PDF** to the same user;
6. Temporary encrypted handling of MOFA session cookies strictly for that lookup;
7. Displaying MOFA attribution on JetPakistan result screens;
8. Whether an **official partner / API interface** exists that should be used **instead** of Visa Platform form mediation.

---

## 4. What JetPakistan will not do without approval

- Production public Visa lookup activation
- CAPTCHA circumvention
- Automated mass queries
- Framing/embedding the Visa Platform against MOFA framing/linking rules without permission
- Claiming JetPakistan independently issues or verifies visas

---

## 5. Fallback if permission is denied or unavailable

JetPakistan will ship (or keep) a simple informational landing that directs customers to the **official** MOFA Visa Platform URL — without server-mediated lookup.

---

## 6. Contact / response

Please reply with written approval, denial, or redirection to an official integration channel, including any required commercial/government registration steps.

**Document control:** JetPakistan internal evidence `jp-visa-mofa-01a` — policy package only.
