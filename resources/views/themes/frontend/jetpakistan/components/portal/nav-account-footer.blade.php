{{-- Profile + Logout — always visible; not permission-gated (Agent Staff must retain access). --}}
@php
    use Illuminate\Support\Facades\Route;
@endphp
<div class="jp-portal__nav-footer" data-testid="jp-portal-nav-account">
    @if (Route::has('profile.edit'))
        <a
            href="{{ client_route('profile.edit') }}"
            @class(['is-active' => request()->routeIs('profile.*')])
            data-testid="jp-portal-sidebar-profile"
        >
            <x-jp.icon name="user" />
            <span>Profile</span>
        </a>
    @endif
    @if (Route::has('logout'))
        <form method="post" action="{{ route('logout') }}" class="jp-portal__logout-form">
            @csrf
            <button type="submit" class="jp-portal__nav-logout" data-testid="jp-portal-sidebar-logout">
                <x-jp.icon name="log-out" />
                <span>Logout</span>
            </button>
        </form>
    @endif
</div>
