# JP-OPS-READINESS-01 Wave 1A — Server Capacity Snapshot

**Probed:** 2026-10-14 ~19:33 UTC server time

| Resource | Value | Assessment |
|----------|-------|------------|
| CPU cores | 6 | — |
| Load average | 5.40 / 5.02 / 4.94 | Elevated but < cores*2; watch |
| Memory | 11Gi total; 3.5Gi used; **8.2Gi available** | OK |
| Swap | 2Gi; 151Mi used | Mild; not critical |
| Disk | 89% used; 12G free | **BLOCKER for deploy headroom** |
| Inodes | 6% | OK |
| Uptime | 77 days | Stable |

## Processes

- PM2 god daemon + 2 next-server (dashboard/public)
- No runaway PHP queue daemons
- No zombie storm observed in top RSS sample

```
SERVER_CAPACITY_BLOCKERS=DISK_89_PERCENT
MEMORY_PRESSURE=NO
SWAP_PRESSURE=LOW
```
