# OneAPI WSDL Contract Discovery

DATE: 2026-11-02  
WSDL_URL: `https://g94.isaaviations.com/webservices/services/AAResWebServices?wsdl`  
SOAP_ENDPOINT: `https://g94.isaaviations.com/webservices/services/AAResWebServices`  
CACHE_PATH: `storage/app/oneapi-wsdl-cache/AAResWebServices.wsdl` (local cache; not committed secrets)

## Hash

ONEAPI_WSDL_SHA256=`3bb629ec328bb0922453733537647a0967cbed2bd41ef52b87db9ebfbc4db0b9`  
WSDL_BYTES=`318844`  
WSDL_DOWNLOAD=`OK`

## Enumerated `wsdl:operation` names (contract-proven)

- batchUpdateFlightInventory
- book
- getAgentAvailableCredit
- getAgentSalesData
- getAgentSeatSellingReport
- getAllPriceAvailability
- getAvailability
- getBaggageDetails
- getFlightInfo
- getFlightInventoryAllocations
- getFlightLoadInfo
- getFlightSchedule
- getInsuranceQuote
- getIOBoundPaxCountInfo
- getItinerary
- getMealDetails
- getMultiMealDetails
- getPassengerCredit
- getPaxTypesForFlightLeg
- getPrice
- getResAuditHistory
- getReservationbyPNR
- getReservationsList
- getSeatMap
- getSSRDetails
- getTaxInvoicebyPNR
- getTermsNConditions
- getUserDefinedSSRDetails
- manualSendEBIDailyReconRpt
- modifyReservation
- modifyResQuery
- ping
- reconPaymentTransactions
- setUserDefinedSSRAmount
- syncPnrTxnsWithEBI
- updateEticketStatus
- updateOptimizedInventory
- updatePFS

## Required certification mapping

| Gate | Value |
|------|-------|
| ONEAPI_PRICE_OPERATION | `getPrice` |
| ONEAPI_BOOK_OPERATION | `book` |
| ONEAPI_READ_OPERATION | `getReservationbyPNR` |
| ONEAPI_MODIFY_QUERY_OPERATION | `modifyResQuery` |
| ONEAPI_MODIFY_OPERATION | `modifyReservation` |
| ONEAPI_TICKETING_OPERATION | NONE (no `ticket` / `issue` / `ticketing` / document-issuance operation name) |

## Ticketing keyword search (no inference)

Hits related to tickets/payments that are **not** a dedicated issuance API:

- `updateEticketStatus` — status update operation name only
- `getTaxInvoicebyPNR` — invoice/read style
- `reconPaymentTransactions` — reconciliation

No WSDL operation literally named for ticket issuance / confirm ticket / document issuance.

## Decision

ONEAPI_TICKETING_CONTRACT_PROVEN=`NO`  
ONEAPI_TICKETING_UAT=`NOT_ATTEMPTED` (hard gate)  
ONEAPI_END_TO_END_SCOPE=`AUTH_SEARCH_PRICE_BOOK_READ` (unless later vendor doc proves issuance)

## JetPakistan SOAPAction binding (existing config)

From `config/suppliers.php` `one_api.soap_operations` (adapter mapping, not WSDL invent):

- price → `OTA_AirPriceRQ`
- baggage → `AA_OTA_AirBaggageDetailsRQ`
- meal → `AA_OTA_AirMealDetailsRQ`
- seat_map → `OTA_AirSeatMapRQ`
- book → `OTA_AirBookRQ`
- read → `OTA_ReadRQ`
- modify → `OTA_AirBookModifyRQ`
