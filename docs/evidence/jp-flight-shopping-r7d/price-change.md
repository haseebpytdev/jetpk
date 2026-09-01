# Price change

`PublicOfferRevalidationPresenter` still requires fare-change acceptance when display total changes.

| Gate | Value |
|---|---|
| STALE_DISPLAYED_PRICE_USED | 0 |
| PRICE_CHANGE_USER_ACK | PASS (existing modal path retained; acceptFareChange also uses passengers_url handoff) |
