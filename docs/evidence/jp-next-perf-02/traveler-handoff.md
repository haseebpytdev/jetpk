# Traveler / Review / Payment

Traveler: soft reload preserved; prefetch `/booking/review` after READY.  
Review root cause: client-only fetch with full-page spinner — now shell+status.  
Payment shell root cause: same class — now shell+status.  
No payment execution in this phase.
