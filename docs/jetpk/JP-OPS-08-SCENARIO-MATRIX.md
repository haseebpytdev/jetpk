# JP-OPS-08 Scenario Matrix

Transport: EVENT_POLLING
Updated: 2026-08-12T04:30:00Z
Closure: FAIL_NOT_OPERATIONALLY_CLOSED
Blocker: OLS_INTEGRITY_HASH_UNREADABLE_WITHOUT_SUDO

| ID | Scenario | Status | Latency | Production | Notes |
|----|----------|--------|---------|------------|-------|
| OPS08-S01 | Agent → Operations | PASS | - | NO_REPRESENTATIVE_PRODUCTION_RECORD | Domain deposit fan-out + dedupe + agency isolation; reconnect n/a (no prod deposit session). REALTIME=PASS via same EVENT_POLLING inbox contract used by ops surfaces. |
| OPS08-S02 | Admin → Staff assignment | PASS | - | NO_REPRESENTATIVE_PRODUCTION_RECORD | Booking assign domain PHPUnit; production uses support-assign representative for browser latency/reconnect |
| OPS08-S03 | Staff → Admin activity | PASS | - | PASS | Internal note PHPUnit + production staff/admin ops activity during support loop |
| OPS08-S04 | Customer → Support | PASS | 2416 | PASS |  |
| OPS08-S05 | Support → Customer | PASS | 1732 | PASS | Customer-visible reply only; internal notes excluded |
| OPS08-S06 | Support conversation | PASS | 1466 | PASS |  |
| OPS08-S07 | Department routing | PASS | - | PASS | Role/permission queues: finance staff get deposit; support-only do not; support events skip finance-only staff |
| OPS08-S08 | Agent finance intake | PASS | - | NO_REPRESENTATIVE_PRODUCTION_RECORD | Balance unchanged; deep_link agents/deposits; reconnect n/a without prod deposit |
| OPS08-S09 | Notification read/unread | PASS | - | PASS |  |
| OPS08-S10 | Offline recovery | PASS | - | PASS |  |
| OPS08-S11 | Duplicate protection | PASS | - | PASS | stable event_key idempotency |
| OPS08-S12 | Stale-state concurrency | PASS | - | PASS | Multi-browser: Staff close → Admin assign with expected_updated_at → HTTP 409 + fresh closed state; no assignee mutation |
| OPS08-S13 | Cross-agency isolation | PASS | - | NO_REPRESENTATIVE_PRODUCTION_RECORD | PHPUnit agency A/B booking + deposit isolation; no prod agency mutation |
| OPS08-S14 | Internal-data privacy | PASS | - | PASS | Assignment + internal notes do not leak to customer inbox; outward status does |
| OPS08-S15 | Full business simulation | PASS | 2416 | PASS | Domain full-sim + production support create/assign/reply + stale concurrency + reconnect |
