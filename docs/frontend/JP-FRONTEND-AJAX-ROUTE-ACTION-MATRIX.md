# JP-FRONTEND AJAX Route Action Matrix

| Action | Classification |
|---|---|
| Login / OTP / register / reset | AJAX preferred; non-JS fallback preserved |
| Airport autocomplete | AJAX required |
| Flight search init | AJAX preferred |
| Results filter/sort | AJAX preferred |
| Fare revalidation | AJAX required |
| Passenger submit | AJAX preferred |
| Booking review create | AJAX preferred |
| Manual payment proof | AJAX via adapter |
| AbhiPay card handoff | Secure external handoff (normal navigation) |
| Payment status poll | AJAX required |
| PDF/invoice download | Download (normal navigation) |
| Logout (Laravel redirect) | Normal navigation |
| Customer/Agent list filter | AJAX preferred |
| Group inventory refresh | AJAX where adapter exists |
| CMS public pages | Server render; no AJAX required |
