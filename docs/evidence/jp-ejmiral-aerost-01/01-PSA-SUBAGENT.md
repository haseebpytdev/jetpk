# PSA / Sub Travel Agent Prerequisite (B1)

PSA portal (admin only): `https://sa.uat.ejmiral.com:7777`

| Gate | Result |
|------|--------|
| PSA_LOGIN | NOT_ATTEMPTED |
| SUB_AGENT_EXISTS | UNKNOWN |
| SUB_AGENT_CREATED | NO |
| SUB_AGENT_FUNDED | NO |
| SUB_AGENT_BALANCE_READY | NO |

## Blocker

No Sub Travel Agent API credentials are present in local encrypted `SupplierConnection` storage or `.env`.  
PSA login requires interactive/admin credentials not available in this agent session without owner-provided secrets.

API booking/ticketing MUST use Sub Travel Agent credentials (never PSA admin as booking identity).
