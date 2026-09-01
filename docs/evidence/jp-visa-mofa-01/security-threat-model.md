# Security threat model

## Threats

| Threat | Mitigation (design) |
|---|---|
| Passport / visa-number enumeration | Human captcha per attempt; rate limits; anomaly detection; no automated bulk lookup |
| IDOR on JP result/PDF | Opaque lookup ids; authz bound to creating session; short TTL |
| Session theft (JP or MOFA jar) | Encrypt MOFA jar at rest in ephemeral store; HttpOnly JP cookies; short TTL |
| PDF token leakage | No public URLs; no-store; no CDN |
| Log leakage | Redact identity fields; structured logging denylist |
| Cache leakage | `private, no-store`; no shared cache |
| SSRF | Strict allowlist of MOFA hosts/paths only; no arbitrary URL fetch |
| Provider-response injection | Parse allowlisted HTML carefully; escape output; never trust MOFA HTML as JP HTML unsanitized |
| Malicious PDF / content-type mismatch | Verify `application/pdf` magic; size caps; disposition controls |
| Oversized response | Hard max bytes on captcha/result/PDF |
| Redirect abuse | Disallow open redirects; only follow allowlisted MOFA paths |
| CAPTCHA abuse / solvers | Human-only; no solver integration |

## Allowlist (conceptual)

Host: `visa.mofa.gov.sa`

Paths (baseline observed):

- `GET /visaservices/searchvisa`
- `POST /visaservices/searchvisa`
- `GET /Base/GetRandomCaptchaImage` (+ `/{id}` or `?{random}`)

PDF path: add only after authorized observation.

## HTTP response security (JP)

```
Cache-Control: private, no-store
X-Robots-Tag: noindex, nofollow
```
