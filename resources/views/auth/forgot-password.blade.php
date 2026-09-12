@extends('layout.auth')

@section('AuthContent')

    <div class="account-pages _my-5 pt-sm-5">
        <div class="container my-5">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">

                    <div class="card overflow-hidden">
                        <div class="bg-primary-subtle">
                            <div class="row">
                                <div class="col-7">
                                    <div class="text-primary p-4">
                                        <h5 class="text-primary">Forgot Password</h5>
                                        <p>Enter your official Phone Kinbo email address.</p>
                                    </div>
                                </div>

                                <div class="col-5 align-self-end">
                                    <img src="{{ asset('images/logo.svg') }}" alt="" class="img-fluid">
                                </div>
                            </div>
                        </div>

                        <div class="card-body pt-0">
                            <div>
                                <div class="avatar-md profile-user-wid mb-4">
                                    <span class="avatar-title rounded-circle">
                                        <img src="{{ asset('images/logo.svg') }}"
                                             alt=""
                                             class="rounded-circle"
                                             height="34">
                                    </span>
                                </div>
                            </div>

                            <div class="p-2">

                                @if(session('success'))
                                    <div class="alert alert-success text-center mb-3" role="alert">
                                        {{ session('success') }}
                                    </div>
                                @endif

                                @if(session('error'))
                                    <div class="alert alert-danger text-center mb-3" role="alert">
                                        {{ session('error') }}
                                    </div>
                                @endif

                                <div class="alert alert-info text-center mb-4" role="alert">
                                    Enter your email and password reset instructions will be sent to you.
                                </div>

                                <form class="form-horizontal"
                                      action="{{ route('password.email') }}"
                                      method="POST">
                                    @csrf

                                    <div class="mb-3">
                                        <label for="email" class="form-label">
                                            Email <span class="text-danger">*</span>
                                        </label>

                                        <input type="email"
                                               name="email"
                                               value="{{ old('email') }}"
                                               class="form-control @error('email') is-invalid @enderror"
                                               id="email"
                                               placeholder="Enter official email">

                                        @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="text-end">
                                        <button class="btn btn-primary w-md waves-effect waves-light"
                                                type="submit">
                                            Send Reset Link
                                        </button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>

                    <div class="mt-5 text-center">
                        <p>
                            <span class="text-dark">Remember password? </span>
                            <a href="{{ route('login.view') }}" class="fw-medium text-white">
                                Sign In here
                            </a>
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>

@endsection
