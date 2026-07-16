{{-- Reusable SVG icon. Usage: <x-jp.icon name="plane" /> or <x-jp.icon name="arrow-right" class="ic-xs" /> --}}
@props(['name'])
<svg {{ $attributes->merge(['class' => 'icon']) }} viewBox="0 0 24 24" aria-hidden="true">
@switch($name)
@case('plane')<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 3.8c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z" stroke="none" fill="currentColor"/>@break
@case('phone')<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>@break
@case('map-pin')<path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>@break
@case('calendar')<rect x="3" y="4" width="18" height="18" rx="2.5"/><path d="M3 10h18M8 2v4M16 2v4"/>@break
@case('users')<path d="M16 21v-2a4 4 0 0 0-8 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/>@break
@case('swap')<path d="M7 16V4M7 4 3 8M7 4l4 4M17 8v12M17 20l4-4M17 20l-4-4"/>@break
@case('search')<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>@break
@case('check')<path d="M20 6 9 17l-5-5"/>@break
@case('shield')<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>@break
@case('shield-check')<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>@break
@case('check-square')<path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>@break
@case('refresh')<path d="M3 12a9 9 0 1 0 9-9M3 12l3-3M3 12l3 3"/><path d="M12 7v5l3 2"/>@break
@case('zap')<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>@break
@case('clock')<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM12 6v6l4 2"/>@break
@case('arrow-right')<path d="M5 12h14M13 6l6 6-6 6"/>@break
@case('chat')<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>@break
@case('menu')<path d="M3 6h18M3 12h18M3 18h18"/>@break
@case('close')<path d="M18 6 6 18M6 6l12 12"/>@break
@case('lock')<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>@break
@case('user')<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>@break
@case('log-out')<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>@break
@case('x')<path d="M4 4l16 16M20 4 4 20"/>@break
@case('instagram')<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>@break
{{-- JP-PORTAL-3 · Portal icons. ADDITIVE ONLY — no existing @case was altered.
     Required because the @default arm silently falls back to the PLANE glyph: any unknown name
     renders an aeroplane instead of failing loudly. These eight names were already in use by
     portal views (and 'message-circle' by the baseline customer support index) and were all
     rendering planes. Drawn in the same 24x24 stroke style as the existing set. --}}
@case('message-circle')<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>@break
@case('wallet')<path d="M3 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1"/><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M16 13h3"/>@break
@case('upload')<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M12 3v13M7 8l5-5 5 5"/>@break
@case('list')<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>@break
@case('list-details')<path d="M13 6h8M13 12h8M13 18h8"/><rect x="3" y="4" width="6" height="6" rx="1"/><rect x="3" y="14" width="6" height="6" rx="1"/>@break
@case('user-shield')<path d="M11 20H4v-2a4 4 0 0 1 4-4h3"/><circle cx="9.5" cy="7" r="4"/><path d="M18 21s3-1.5 3-4v-3l-3-1-3 1v3c0 2.5 3 4 3 4z"/>@break
@case('building-store')<path d="M3 21h18M4 21V10M20 21V10"/><path d="M3 7l1.5-4h15L21 7a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0z"/><path d="M9 21v-6h6v6"/>@break
@case('info-circle')<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>@break
@default<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 3.8c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z" stroke="none" fill="currentColor"/>
@endswitch
</svg>
