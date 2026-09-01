# Security public

CHAT_XSS=**PASS**  
CHAT_IDOR=**PASS**  
LANGUAGE_PIPELINE_TOOL_ESCAPE=**0**  
SECRET_DISCLOSURE=**0**  
SQL_EXECUTION=**0**  
SHELL_EXECUTION=**0**  
OTHER_CUSTOMER_DATA_DISCLOSURE=**0**  
AI_PRIVATE_BOOKING_DATA_ACCESS=**0**  
AI_TRANSACTIONAL_TOOL_ESCAPE=**0**  
CHAT_RATE_LIMIT=**PASS**

Controlled public negatives: XSS/script, SQL/shell-looking, prompt-injection, `.env`/secret requests, other conversation IDs, oversized input, Unicode edges.
