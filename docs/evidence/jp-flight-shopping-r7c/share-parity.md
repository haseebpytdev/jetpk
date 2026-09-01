# Share parity

ResultShareActions extracted and wired on:

- FlightResultCard (One Way)
- PairReturnCard (Return Paired)
- OutboundOptionCard (Segmented outbound)
- ReturnOptionsPage (Segmented return)

Uses existing `/api/public/share/flight` short-link authority with trip_type + return_date.

Live: Paired shareCopy=12 shareWa=12; Segmented shareCopy=12 shareWa=12.

RETURN_PAIRED_COPY=PASS  
RETURN_PAIRED_WHATSAPP=PASS  
RETURN_SEGMENTED_COPY=PASS  
RETURN_SEGMENTED_WHATSAPP=PASS  
RETURN_SHARE_TRIP_TYPE=RETURN (when round_trip params present)  
RETURN_SHARE_BUTTON_LAYOUT=PASS (price column, above Details/Book)
