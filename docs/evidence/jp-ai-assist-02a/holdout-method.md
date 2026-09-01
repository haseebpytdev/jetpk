# Holdout method

1. Freeze hybrid intent after 01F tip; implement 02A gates separately.  
2. Generate independent corpus `tests/Fixtures/ai/hybrid-holdout-02a.json` (≥500) with routes/vocabulary not copied from 01F.  
3. Evaluate without per-phrase hard-codes in production.  
4. Genuine defects fixed generically (multi-word cities, short IATA false positives, return-before-depart, flights-from polite prefix).  
5. Regenerate holdout; re-certify.

Generator: `ai-assistant/benchmarks/generate-hybrid-holdout-02a.php`
