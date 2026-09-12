# Router-wait trace analysis (Authority-06 R3)

Captured: 2026-09-11T02:49:50.674Z
Production SHA: 8c50fc61967e51c5b576375b55ae7701d122c121
Slow threshold: >=1200ms usable
Samples: 12

## ROUTER_WAIT_TRACE_DOMINANT_CAUSE

CHUNK_PARSE_EVAL

## Representative slow samples

| route | RSC_END | ROUTE_COMMIT | INTERVAL_MS | MAIN_BUSY | MAIN_IDLE | NET_CB | LONGEST_TASK | DOMINANT |
|-------|---------|--------------|-------------|-----------|-----------|--------|--------------|----------|
| home_to_login | 12583 | 10393 | -2190 | 0 | 0 | 25415 | 0 | CHUNK_PARSE_EVAL |
| home_to_login | 17927 | 16662 | -1265 | 0 | 0 | 39361 | 0 | CHUNK_PARSE_EVAL |
| home_to_login | 2284 | 4067 | 1783 | 140 | 1643 | 4871 | 71 | CHUNK_PARSE_EVAL |
| home_to_login | 13766 | 12713 | -1053 | 0 | 0 | 34348 | 0 | CHUNK_PARSE_EVAL |
| home_to_login | 16702 | 14493 | -2209 | 0 | 0 | 62008 | 0 | CHUNK_PARSE_EVAL |
| home_to_login | 15571 | 14362 | -1209 | 0 | 0 | 32985 | 0 | CHUNK_PARSE_EVAL |
| home_to_support | 11471 | 10162 | -1309 | 0 | 0 | 17028 | 0 | CHUNK_PARSE_EVAL |
| home_to_support | 14696 | 14612 | -84 | 0 | 0 | 67409 | 0 | CHUNK_PARSE_EVAL |
| home_to_support | 15579 | 14021 | -1558 | 0 | 0 | 35749 | 0 | CHUNK_PARSE_EVAL |
| home_to_support | 17114 | 16071 | -1043 | 0 | 0 | 35775 | 0 | CHUNK_PARSE_EVAL |
| home_to_support | 12934 | 13666 | 732 | 0 | 732 | 61037 | 0 | CHUNK_PARSE_EVAL |
| home_to_support | 4673 | 4632 | -41 | 0 | 0 | 15093 | 0 | CHUNK_PARSE_EVAL |

## Per-sample notes

### home_to_login #1

- CHUNK_PARSE_EVAL; longTasks=0ms sessionNet=1297ms configNet=1296ms rscNet=22822ms chunks=23047ms
- trace: `home_to_login-slow-1.zip`

### home_to_login #2

- CHUNK_PARSE_EVAL; longTasks=0ms sessionNet=335ms configNet=396ms rscNet=38630ms chunks=49004ms
- trace: `home_to_login-slow-2.zip`

### home_to_login #3

- CHUNK_PARSE_EVAL; longTasks=140ms sessionNet=1445ms configNet=1444ms rscNet=1982ms chunks=13998ms
- trace: `home_to_login-slow-3.zip`

### home_to_login #4

- CHUNK_PARSE_EVAL; longTasks=0ms sessionNet=414ms configNet=415ms rscNet=33519ms chunks=41471ms
- trace: `home_to_login-slow-4.zip`

### home_to_login #5

- CHUNK_PARSE_EVAL; longTasks=0ms sessionNet=1008ms configNet=919ms rscNet=60081ms chunks=90725ms
- trace: `home_to_login-slow-5.zip`

### home_to_login #6

- CHUNK_PARSE_EVAL; longTasks=0ms sessionNet=474ms configNet=487ms rscNet=32024ms chunks=39375ms
- trace: `home_to_login-slow-6.zip`

### home_to_support #7

- CHUNK_PARSE_EVAL; longTasks=0ms sessionNet=490ms configNet=571ms rscNet=15967ms chunks=30418ms
- trace: `home_to_support-slow-7.zip`

### home_to_support #8

- CHUNK_PARSE_EVAL; longTasks=0ms sessionNet=0ms configNet=0ms rscNet=67409ms chunks=50764ms
- trace: `home_to_support-slow-8.zip`

### home_to_support #9

- CHUNK_PARSE_EVAL; longTasks=0ms sessionNet=351ms configNet=413ms rscNet=34985ms chunks=35550ms
- trace: `home_to_support-slow-9.zip`

### home_to_support #10

- CHUNK_PARSE_EVAL; longTasks=0ms sessionNet=488ms configNet=488ms rscNet=34799ms chunks=35090ms
- trace: `home_to_support-slow-10.zip`

### home_to_support #11

- CHUNK_PARSE_EVAL; longTasks=0ms sessionNet=0ms configNet=0ms rscNet=61037ms chunks=39058ms
- trace: `home_to_support-slow-11.zip`

### home_to_support #12

- CHUNK_PARSE_EVAL; longTasks=0ms sessionNet=0ms configNet=0ms rscNet=15093ms chunks=22188ms
- trace: `home_to_support-slow-12.zip`
