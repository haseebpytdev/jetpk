# OneAPI Price (A5)

DATE: 2026-11-02

## Contract

WSDL operation: `getPrice`  
JetPakistan SOAPAction binding: `OTA_AirPriceRQ`

## Offline

Fixture `tests/Fixtures/Suppliers/OneApi/price_base.xml` proves:

- TransactionIdentifier capture
- total/base fare + currency parse
- bundle/SSR context hooks

## Live UAT

ONEAPI_PRICE=`NOT_ATTEMPTED_LIVE`  
PRICE_MATCH_CONTEXT=`NOT_ATTEMPTED_LIVE`  
SESSION_CAPTURE=`PASS` (offline) / `NOT_ATTEMPTED_LIVE`

Blocked by: encrypted SupplierConnection UAT credentials absent locally.
