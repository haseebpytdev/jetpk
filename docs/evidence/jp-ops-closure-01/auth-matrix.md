# Auth / Google / existing-email (JP-OPS-CLOSURE-01)

```
GOOGLE_CODE_COMPLETE=YES
GOOGLE_CONFIG_PRESENT=YES (env placeholders)
GOOGLE_PRODUCTION_ENABLED=NOT_VERIFIED_THIS_RUN
GOOGLE_CALLBACK_URL=env GOOGLE_REDIRECT_URI or {APP_URL}/auth/google/callback
GOOGLE_CHECKOUT_RETURN_SUPPORTED=YES (CheckoutReturnIntent)
GOOGLE_SECRET_IN_GIT=NO
GOOGLE_EXISTING_CUSTOMER=NOT_TESTED_LIVE
GOOGLE_NEW_CUSTOMER=NOT_TESTED_LIVE
GOOGLE_PRIVILEGED_SAFETY=CODE_PASS (matcher + SocialAuthController block new privileged link)
GOOGLE_CHECKOUT_RESUME=CODE_PASS
```

## Existing-email checkout (engineered this run)

```
PUBLIC_EMAIL_ENUMERATION=NO (context-bound POST /booking/checkout/guest-email)
RATE_LIMITING=checkout-guest-email 8/min by session+IP
CHECKOUT_CONTEXT_REQUIRED=YES (ota_booking_draft or public booking session)
CUSTOMER_MATCH_ONLY=YES
PRIVILEGED_MATCH=false (same body as unknown)
SIGN_IN_CONTINUE=Next banner → /login?redirect=&checkout_return=
CONTINUE_AS_GUEST=dismisses banner; create_account forced false
GUEST_ACCOUNT_ISOLATION=unchanged (no silent customer_id attach)
```

Layer A tests: `CheckoutGuestEmailRecognitionTest` PASS
