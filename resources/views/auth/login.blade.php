@extends('layouts.dashboard', ['title' => 'Login'])

@section('content')
    <div class="login-wrap">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <div class="eyebrow">Admin</div>
                    <h1>Dashboard Login</h1>
                    <div class="subtitle">Sign in to monitor payment activity and gateway configuration.</div>
                </div>
            </div>
            <form method="POST" action="{{ route('login.store') }}" class="form-body">
                @csrf
                <label class="field">
                    <span class="label">Email</span>
                    <input class="input" name="email" type="email" value="{{ old('email') }}" required autofocus>
                </label>
                <label class="field">
                    <span class="label">Password</span>
                    <input class="input" name="password" type="password" required>
                </label>
                <label class="check-row">
                    <input name="remember" type="checkbox" value="1">
                    <span>Remember me</span>
                </label>
                @if ($errors->any())
                    <div class="alert">{{ $errors->first() }}</div>
                @endif
                <button class="button primary" type="submit">Login</button>
            </form>
        </div>
    </div>
@endsection
