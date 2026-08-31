# Group short-link proof

## Engineering truth
- GROUP_SHORT_LINK_CREATE_ENDPOINT=`POST /api/public/share/group`
- GROUP_SHORT_LINK_RESOLVE_ROUTE=`GET /g/{code}` (`share.group`)
- GROUP_SHORT_LINK_PUBLIC_PAGE=`resources/views/frontend/share/group-landing.blade.php` (branded public-safe landing; not silent-only redirect)
- GROUP_SHORT_LINK_EXPIRY=link `expires_at` enforced
- GROUP_SHORT_LINK_INVALID_STATE=`frontend.share.invalid`
- GROUP_SHORT_LINK_EXPIRED_STATE=`frontend.share.expired-group`
- GROUP_SHORT_LINK_BOOK_CONTINUATION=`View & continue` → `/groups/{package_id}` or `/groups`

## Public-safe
No supplier cost, private notes, or admin metadata on landing.

## Mobile matrix
Valid / expired / invalid captured at 390 and 768. Overflow=0. Action reachable on valid.

GROUP_SHORT_LINK=PASS
MOBILE_GROUP_SHORT_LINK=PASS
GROUP_SHORT_LINK_VALID=PASS
GROUP_SHORT_LINK_EXPIRED=PASS
GROUP_SHORT_LINK_INVALID=PASS
GROUP_LINK_PAGE_OVERFLOW=0
GROUP_LINK_ACTION_REACHABLE=YES
