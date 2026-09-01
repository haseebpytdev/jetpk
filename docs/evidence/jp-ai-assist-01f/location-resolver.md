# Location resolver

`LocationResolver` owns city→IATA using JetPakistan allowlist.  
Invented IATA rejected. London / New York are **ambiguous** (clarification options) — `AMBIGUOUS_LOCATION_AUTO_GUESS=0`.  

Supports codes, English names, Roman Urdu, Urdu script, mixed `ISB to دبئی`, and `Jeddah flights from LHE`.
