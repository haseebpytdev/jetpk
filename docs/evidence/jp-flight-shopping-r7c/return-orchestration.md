# Return orchestration

Flow: Laravel RT search → `return_pair` / SUPPLIER_RETURNED paired_options → client poll until ready → PairReturnCard list.

States: idle → searching/partial → ready | empty | error | timeout (client deadline).

RETURN_SEARCH_STATE_MACHINE=PASS  
PAIRED_VIEW_COMPLETES=PASS  
SEGMENTED_VIEW_COMPLETES=PASS (12 outbound cards + share)  
SEGMENTED_VIEW_STATE_CONTAMINATION=0  
RETURN_INFINITE_SKELETON=0  
RETURN_TIMEOUT_UI=PASS (deadline messaging in hook)
