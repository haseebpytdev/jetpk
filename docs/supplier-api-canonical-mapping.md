# JetPakistan Supplier API Canonical Mapping

This document is the authoritative supplier/API terminology reference for JetPakistan. Use it in implementation notes, Admin labels, Cursor/Codex prompts, audits, UAT reports, and future supplier work.

## Canonical mapping

| Supplier | Canonical JetPakistan integration | Notes |
|---|---|---|
| Sabre | Sabre GDS / Sabre supplier integration | Separate from the direct PIA and AirBlue integrations. |
| PIA | **PIA NDC — Hitit / Crane 20.1** | Hitit Crane NDC 20.1 belongs to the PIA direct NDC integration in this project. |
| AirBlue | **AirBlue NDC/API — Zapways** | Zapways is the AirBlue direct supplier API/NDC integration used by JetPakistan. |

## Mandatory terminology rules

- **PIA NDC = Hitit / Crane 20.1.**
- **AirBlue NDC/API = Zapways only.**
- Do **not** describe Hitit, Crane, Crane 20.1, or Crane NDC as an AirBlue channel.
- Do **not** use labels such as `AirBlue / Crane NDC`, `AirBlue (Crane / Zapways)`, or `AirBlue Crane/Hitit` in new documentation, UI, prompts, or implementation notes.
- Any reference to Hitit/Crane 20.1 should be associated with the **PIA NDC** supplier path.
- Any direct AirBlue supplier/API reference should be associated with **Zapways**.
- Keep all supplier credentials and secrets out of documentation, source control, logs, screenshots, and prompts.

## Deprecated / misleading historical references

Some existing configuration and Admin metadata historically mixed the supplier identities. These references are **not architectural truth** and must not be used to infer supplier ownership.

| Historical / deprecated reference | Correct interpretation |
|---|---|
| `AirBlue / Crane NDC` | **PIA NDC — Hitit / Crane 20.1** |
| `AirBlue (Crane / Zapways)` | Split the concepts: **PIA NDC = Hitit/Crane 20.1**; **AirBlue = Zapways** |
| AirBlue channel option `CRANE_NDC` | Historical misclassification; Crane belongs to PIA NDC |
| AirBlue configuration pointing to `app.crane.aero/.../cranendc/v20.1/...` | Historical misclassification; that endpoint belongs to the PIA/Hitit integration |
| `AIRBLUE_NDC_ENDPOINT` used for a Crane endpoint | Historical naming/configuration debt; do not use it as documentation authority |

Do not remove or rewrite runtime configuration solely because of this terminology correction. Functional supplier-routing changes require a separate code audit and regression test so production search/booking behavior is not broken.

## Current focused status

- **Sabre:** integration considered done for the current workstream.
- **PIA NDC / Hitit Crane 20.1:** integration exists; remaining production proof is an isolated public JetPakistan search with Sabre temporarily disabled and PIA enabled, then Sabre restored.
- **AirBlue / Zapways:** audit the existing local implementation first, then configure the supplier-provided Zapways test environment, run certification, and only then activate production credentials.
- **Duffel and other suppliers:** outside the current focused workstream unless explicitly reopened.

## AirBlue Zapways configuration rule

The current supplier-provided Zapways Test and Production connection details must be stored through the approved secret/configuration mechanism only. Do not commit endpoints together with credentials or expose API client keys, agent passwords, or other secrets in this document.

## Related supplier distinction

Turkish Airlines NDC documentation or onboarding material is a separate supplier integration and must not be treated as part of AirBlue/Zapways.
