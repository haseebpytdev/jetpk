@php
    $dashSubtitle = $dashSubtitle ?? 'Operations';
    $userInitials = $userInitials ?? 'U';
@endphp
<header class="jp-dash__header">
    <button type="button" class="jp-iconbtn jp-iconbtn--menu" data-jp-side-toggle aria-label="Toggle sidebar">
        <span aria-hidden="true">☰</span>
    </button>
    <button type="button" class="jp-iconbtn jp-iconbtn--menu jp-only-mobile" data-jp-side-open aria-label="Open menu">
        <span aria-hidden="true">☰</span>
    </button>

    <div class="jp-dash-search jp-hide-mobile" role="search">
        <input type="search" class="jp-dash-search__input" placeholder="Search bookings, customers…" aria-label="Quick search" data-jp-dash-search disabled>
    </div>

    <div class="jp-spacer"></div>

    <button type="button" class="jp-iconbtn" data-jp-theme-toggle aria-label="Toggle day/night theme">
        <span aria-hidden="true">◐</span>
    </button>

    @auth
        <div class="jp-profile-wrap" data-jp-profile-wrap>
            <button type="button" class="jp-profile" data-jp-profile-toggle aria-expanded="false" aria-haspopup="true">
                <span class="jp-avatar">{{ $userInitials }}</span>
                <span class="jp-profile__meta jp-hide-mobile">
                    <b>{{ auth()->user()->name ?: auth()->user()->email }}</b>
                    <small>{{ $dashSubtitle }}</small>
                </span>
            </button>
            <div class="jp-profile-menu" data-jp-profile-menu hidden>
                <div class="jp-profile-menu__head">
                    <strong>{{ auth()->user()->name ?: 'Account' }}</strong>
                    <span>{{ auth()->user()->email }}</span>
                </div>
                <a href="{{ client_route('profile.edit') }}" class="jp-profile-menu__link">Profile</a>
                @if (request()->routeIs('admin.*') || request()->routeIs('client.admin.*'))
                    <a href="{{ client_route('admin.dashboard') }}" class="jp-profile-menu__link">Dashboard</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="jp-profile-menu__link jp-profile-menu__link--btn">Log out</button>
                </form>
            </div>
        </div>
    @endauth
</header>
