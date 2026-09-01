# Tool integration

`AiShoppingTools::searchFlights` / `searchGroups` — read-only deep links / inventory read.  
Supplier mutations not invoked. Fares never invented by language layer.  

Feature test `PublicAiAssistantTest` proves structured LHE→DXB path with `AI_FLIGHT_SEARCH_READ_CALLS≥1` without live model.  
Group destination-led queries use existing group search authority; booking auth bypass = 0.
