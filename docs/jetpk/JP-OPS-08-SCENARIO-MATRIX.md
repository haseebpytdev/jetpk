# JP-OPS-08 Scenario Matrix

Transport: EVENT_POLLING
Updated: 2026-08-11T21:50:00Z

| ID | Scenario | Status | Latency | Production | Notes |
|----|----------|--------|---------|------------|-------|
| OPS08-S01 | Agent → Operations | PASS | - | NO_REPRESENTATIVE_PRODUCTION_RECORD | Agent deposit domain fan-out PASS; no prod deposit created to avoid ledger clutter |
| OPS08-S02 | Admin → Staff assignment | PASS | - | NO_REPRESENTATIVE_PRODUCTION_RECORD | Booking assign domain PHPUnit PASS; prod QA admin booking list empty — no commercial fixture created |
| OPS08-S03 | Staff → Admin activity | PASS | - | PARTIAL | Staff note→admin inbox PHPUnit; support reply production covers staff→customer |
| OPS08-S04 | Customer → Support | PASS | 1984 | PASS |  |
| OPS08-S05 | Support → Customer | PASS | 1392 | PASS |  |
| OPS08-S06 | Support conversation | PASS | 1664 | PASS | create→assign→staff reply browser |
| OPS08-S07 | Department routing | PASS | - | PENDING | role/permission queues not invented departments |
| OPS08-S08 | Agent finance intake | PASS | - | NO_REPRESENTATIVE_PRODUCTION_RECORD | submitDepositRequest fan-out; balance unchanged |
| OPS08-S09 | Notification read/unread | PASS | - | PASS | mark-read PHPUnit + unread summary production |
| OPS08-S10 | Offline recovery | PASS | - | PASS |  |
| OPS08-S11 | Duplicate protection | PASS | - | PASS | stable event_key dedupe |
| OPS08-S12 | Stale-state concurrency | PASS | - | PENDING | closed ticket rejects stale reply |
| OPS08-S13 | Cross-agency isolation | PASS | - | PENDING |  |
| OPS08-S14 | Internal-data privacy | PASS | - | PASS | internal notes not customer-visible |
| OPS08-S15 | Full business simulation | PASS | - | PASS | domain full-sim + production support loop |
