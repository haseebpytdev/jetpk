# CMS upload size probe — production (final)

**SHA:** `20e921661da55e121a9b2353cba535b350613493`  
**Captured:** 2026-09-08T13:32:28Z  
**Method:** Playwright multipart (`ctx.request.post`), QA admin session

```
2048KB_UPLOAD=200 ok=true bytes=2097152 message=Asset uploaded.
2250KB_UPLOAD=200 ok=true bytes=2304000 message=Asset uploaded.
5000KB_OR_NEAR_LIMIT_BEHAVIOR=200 ok=true bytes=5120000 message=Asset uploaded.
```

Infrastructure: `upload_max_filesize=6M`, `post_max_size=8M` (owner-verified). No further PHP/LiteSpeed changes made during this tick.
