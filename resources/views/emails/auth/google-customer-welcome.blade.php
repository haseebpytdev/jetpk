<x-mail::message>
# Welcome to {{ $brandName }}

Hello {{ $user->name }},

Your customer account is ready. You can sign in with Google and manage bookings from your dashboard.

**Account details**

- Name: **{{ $user->name }}**
- Email: **{{ $user->email }}**

<x-mail::button :url="$dashboardUrl">
Go to my account
</x-mail::button>

If you did not create this account, please contact us immediately at [{{ $supportEmail }}](mailto:{{ $supportEmail }}).

Thanks,<br>
{{ $brandName }}
</x-mail::message>
