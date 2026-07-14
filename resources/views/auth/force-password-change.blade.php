@extends(client_layout('frontend', 'frontend'))

@section('title', 'Change Your Password')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h1 class="h4 mb-2">Change your password</h1>
                        <p class="text-secondary small mb-4">
                            For security, you must set a new password before continuing.
                        </p>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('password.force.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="password" class="form-label">New password</label>
                                <input type="password" name="password" id="password" class="form-control" required autocomplete="new-password">
                            </div>
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">Confirm password</label>
                                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Save password</button>
                        </form>

                        <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm">Log out instead</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
