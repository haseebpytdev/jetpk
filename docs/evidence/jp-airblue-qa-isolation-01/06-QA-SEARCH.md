# QA search

Bootstrap: `GET/POST /staff/qa/airblue-search`

Activate redirects to `/flights/results?from=LHE&to=KHI&...` with QA session active.

Store payload (server-authoritative):

```
qa_scope=true
qa_scope_type=airblue_sandbox
qa_connection_id=23
qa_actor_user_id=<actor>
qa_created_at / qa_expires_at
source_channel=qa_staff_airblue
```

Short-ref mint skipped for QA searches. Resolve of any QA-targeted short-ref returns 410.
