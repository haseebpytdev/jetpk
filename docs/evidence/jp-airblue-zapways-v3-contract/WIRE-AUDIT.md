# JetPakistan v3 wire audit vs otatest4 WSDL + Zapways v3.00

## SOAPActions

All TEST operations match live WSDL/?op (see `_wsdl-parse.json`).
Config defaults updated to namespace form for TEST only; LIVE Read host unchanged.

## Request builders (`AirBlueOtaXmlBuilder`)

| Operation | Namespace 3.0 | Notes |
| --- | --- | --- |
| AirLowFareSearch | YES | Existing fixtures |
| AirBook | YES | BookingReferenceID / travelers |
| Read | YES | UniqueID ID only; Type=14 absent; Instance optional |
| AirSeatMap | YES | SeatMapRequests → FlightSegmentInfo + FareType/CabinClass |
| AirAncillaryItems | YES | v3-only gate |
| AirBookModify Type=5 | YES | SeatRequest: SeatNumber, RowNumber, TravelerRefNumberRPHList, FlightRefNumberRPHList |
| AirDemandTicket | YES | PaymentInfo Amount/Currency/PaymentType |
| Cancel | YES | UniqueID Type=14 for cancel path |

## Response parser (`AirBlueOtaXmlParser`)

| Area | Status |
| --- | --- |
| Documented RowInfo → SeatInfo → Summary | Implemented this PR |
| Summary SeatNumber / AvailableInd / OccupiedInd | Implemented |
| Availability text (SeatAvailable / NoSeatHere) | Implemented |
| Service Code="Cost" → Fee Amount/CurrencyCode | Implemented |
| is_available / is_complimentary / is_paid | Conservative classification added |
| Legacy direct-Seat fallback | Retained for backward compatibility |
| Traveler/Flight RPH on reserved seats | Present on reserved-seat path |

## Architecture

ProtocolGuard / pending-search block / certified-v3 priority: unchanged and covered by unit tests.
