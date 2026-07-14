@extends('layouts.developer')

@section('title', 'Change Password')

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">Change your password</h1>
    <p class="text-secondary mb-0">Set a new password before accessing the Developer Control Panel.</p>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('dev.cp.password.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="password" class="form-label">New password</label>
                            <input type="password" name="password" id="password" class="form-control" required autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-primary">Save password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
