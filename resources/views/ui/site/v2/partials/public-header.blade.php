<header class="ota-v2-public-header" data-testid="v2-public-header">
    @if ($slimTopbar['is_enabled'] ?? true)
        @php
            $topbarStyle = '';
            foreach ($slimTopbar['css_variables'] ?? [] as $var => $value) {
                $topbarStyle .= $var.': '.$value.'; ';
            }
        @endphp
        <div class="ota-v2-public-header__utility" @if($topbarStyle !== '') style="{{ trim($topbarStyle) }}" @endif>
            <div class="ota-v2-page-wrap ota-v2-public-header__utility-inner">
                @foreach ($slimTopbar['items'] ?? [] as $topbarItem)
                    @php
                        $topbarUrl = $topbarItem['url'] ?? null;
                        $topbarLabel = $topbarItem['label'] ?? '';
                        $topbarIcon = $topbarItem['icon'] ?? 'fa-circle';
                    @endphp
                    @if ($topbarUrl)
                        <a href="{{ e($topbarUrl) }}" class="ota-v2-public-header__utility-item"
                           @if(str_starts_with($topbarUrl, 'http')) target="_blank" rel="noopener noreferrer" @endif>
                            <i class="fa {{ $topbarIcon }}" aria-hidden="true"></i>
                            <span>{{ $topbarLabel }}</span>
                        </a>
                    @else
                        <span class="ota-v2-public-header__utility-item">
                            <i class="fa {{ $topbarIcon }}" aria-hidden="true"></i>
                            <span>{{ $topbarLabel }}</span>
                        </span>
                    @endif
                @endforeach
                @if (count($slimTopbar['items'] ?? []) === 0)
                    <span class="ota-v2-public-header__utility-item">
                        <i class="fa fa-headphones" aria-hidden="true"></i>
                        <span>24/7 travel support</span>
                    </span>
                    <span class="ota-v2-public-header__utility-item">
                        <i class="fa fa-lock" aria-hidden="true"></i>
                        <span>Secure booking</span>
                    </span>
                @endif
            </div>
        </div>
    @endif

    <div class="ota-v2-public-header__bar">
        <div class="ota-v2-page-wrap ota-v2-public-header__inner">
            <a href="{{ client_route('home') }}"
               @class(['ota-v2-public-header__brand', 'ota-v2-public-header__brand--logo' => $hasHeaderLogo])
               title="{{ $hasHeaderLogo ? $brandName : ($brandTagline !== '' ? $brandTagline : $brandName) }}"
               @if($hasHeaderLogo) aria-label="{{ e($brandName) }}" @endif>
                @if ($hasHeaderLogo)
                    <img src="{{ $headerLogoUrl ?? asset('storage/'.$logoPath) }}" alt="{{ e($brandName) }}" class="ota-v2-public-header__logo">
                @else
                    <span class="ota-v2-public-header__mark" aria-hidden="true"><i class="fa fa-plane"></i></span>
                    <span class="ota-v2-public-header__name" data-testid="header-brand-name">{{ $brandName }}</span>
                @endif
            </a>

            <input type="checkbox" id="ota-v2-nav-open" class="ota-v2-nav-toggle" autocomplete="off" tabindex="-1">
            <label for="ota-v2-nav-open" class="ota-v2-public-header__burger" data-v2-mobile-nav-toggle aria-controls="ota-v2-mobile-nav" aria-expanded="false" aria-label="Open menu">
                <i class="fa fa-bars" aria-hidden="true"></i>
            </label>
            <label for="ota-v2-nav-open" class="ota-v2-public-header__backdrop" aria-hidden="true"></label>

            <nav id="ota-v2-mobile-nav" class="ota-v2-public-header__nav" aria-label="Primary">
                <div class="ota-v2-public-header__links" data-testid="public-nav-desktop">
                    @if ($navPublicSite)
                        <a href="{{ client_route('home') }}" @class(['ota-v2-public-header__link', 'is-active' => request()->routeIs('home')])>Home</a>
                    @endif
                    @if ($navBookingLookup)
                        <a href="{{ client_route('booking.lookup') }}" @class(['ota-v2-public-header__link', 'is-active' => request()->routeIs('booking.lookup')])>Booking</a>
                    @endif
                    @if ($navSupport)
                        <a href="{{ client_route('support') }}" @class(['ota-v2-public-header__link', 'is-active' => request()->routeIs('support')])>Support</a>
                    @endif
                    @if ($navPublicSite)
                        <a href="{{ client_route('about') }}" @class(['ota-v2-public-header__link', 'is-active' => request()->routeIs('about')])>About</a>
                    @endif
                </div>

                <div class="ota-v2-public-header__actions">
                    @auth
                        <x-account-dropdown variant="desktop" />
                    @else
                        <a href="{{ client_route('login') }}" class="ota-v2-btn ota-v2-btn--ghost ota-v2-btn--compact ota-v2-public-header__button">Login</a>
                        @if ($navCustomerRegistration)
                            <a href="{{ client_route('register') }}" class="ota-v2-btn ota-v2-btn--primary ota-v2-btn--pill ota-v2-btn--compact ota-v2-public-header__button">Sign up</a>
                        @elseif ($navAgentApplications)
                            <a href="{{ client_route('agent.register') }}" class="ota-v2-btn ota-v2-btn--primary ota-v2-btn--pill ota-v2-btn--compact ota-v2-public-header__button" data-testid="public-nav-agent-registration">Agent Registration</a>
                        @endif
                    @endauth
                </div>

                <div class="ota-v2-public-header__mobile-menu" data-testid="public-nav-mobile">
                    @if ($navPublicSite)
                        <a href="{{ client_route('home') }}" @class(['ota-v2-public-header__mobile-link', 'is-active' => request()->routeIs('home')])>Home</a>
                    @endif
                    @if ($navBookingLookup)
                        <a href="{{ client_route('booking.lookup') }}" @class(['ota-v2-public-header__mobile-link', 'is-active' => request()->routeIs('booking.lookup')])>Booking</a>
                    @endif
                    @if ($navSupport)
                        <a href="{{ client_route('support') }}" @class(['ota-v2-public-header__mobile-link', 'is-active' => request()->routeIs('support')])>Support</a>
                    @endif
                    @if ($navPublicSite)
                        <a href="{{ client_route('about') }}" @class(['ota-v2-public-header__mobile-link', 'is-active' => request()->routeIs('about')])>About</a>
                    @endif
                    @auth
                        <x-account-dropdown variant="mobile" />
                    @else
                        <a href="{{ client_route('login') }}" class="ota-v2-btn ota-v2-btn--ghost ota-v2-public-header__mobile-cta">Login</a>
                        @if ($navCustomerRegistration)
                            <a href="{{ client_route('register') }}" class="ota-v2-btn ota-v2-btn--primary ota-v2-public-header__mobile-cta">Sign up</a>
                        @endif
                        @if ($navAgentApplications)
                            <a href="{{ client_route('agent.register') }}" class="ota-v2-btn ota-v2-btn--ghost ota-v2-public-header__mobile-cta" data-testid="public-nav-mobile-agent-registration">Agent Registration</a>
                        @endif
                    @endauth
                </div>
            </nav>
        </div>
    </div>
</header>
