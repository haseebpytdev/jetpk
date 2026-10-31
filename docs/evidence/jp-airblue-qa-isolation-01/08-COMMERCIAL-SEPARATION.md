# Commercial separation

Nested supplier-cost leak (fallback_details) remains CLOSED LIVE (PR #157).

Public/QA browser responses must omit:

- supplier_total / supplier_total_source
- base_fare / taxes (where commercially sensitive)
- markup / internal pricing_components

Customer totals remain available.
