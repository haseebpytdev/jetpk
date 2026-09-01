# Policy gate

Server authoritative: `VisaPolicyGate`

Requires for live: module_enabled AND provider_enabled AND policy_approved AND transport=live.

Current defaults: all false → LIVE_MOFA_CALL_WHEN_POLICY_FALSE=DENIED  
MOFA_POLICY_APPROVED=NO  
WRITTEN_POLICY_APPROVAL_RECEIVED=NO  
POLICY_FEASIBILITY=PENDING
