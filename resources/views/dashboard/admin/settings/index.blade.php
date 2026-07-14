@extends(client_layout('dashboard', 'admin'))

@section('title', 'Settings')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle">Administration</div>
            <h1 class="jp-page-title">Settings</h1>
            <p class="text-secondary mb-0">Manage branding, communications, payments policy, pricing, and promos from one place.</p>
        </div>
    </div>
@endsection

@section('content')
    <div class="row row-cards">
        @foreach ($cards as $card)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route($card['route']) }}" class="card card-link card-link-pop h-100 text-reset text-decoration-none">
                    <div class="jp-card__body">
                        <div class="d-flex align-items-start gap-3">
                            <span class="avatar bg-primary-lt text-primary">
                                <i class="ti {{ $card['icon'] }}"></i>
                            </span>
                            <div>
                                <h3 class="jp-card__title mb-1">{{ $card['title'] }}</h3>
                                <p class="text-secondary small mb-0">{{ $card['description'] }}</p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endsection
