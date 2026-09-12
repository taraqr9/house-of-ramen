@extends('layout.auth', ['page_title' => '403 Unauthorized'])

@section('AuthContent')

    <div class="account-pages my-5 pt-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="text-center mb-5">
                        <h1 class="display-2 fw-medium">
                            4<i class="bx bx-lock-alt text-danger display-3"></i>3
                        </h1>

                        <h4 class="text-uppercase">Access denied</h4>

                        <p class="text-muted">
                            You do not have permission to access this page.
                        </p>

                        <div class="mt-5 text-center">
                            @if(app('impersonate')->isImpersonating())
                                <form method="POST"
                                      action="{{ route('users.impersonate.leave') }}"
                                      class="d-inline-block">
                                    @csrf

                                    <button type="submit"
                                            class="btn btn-warning waves-effect waves-light">
                                        <i class="bx bx-log-out-circle me-1"></i>
                                        Leave Impersonation
                                    </button>
                                </form>
                            @else
                                <a class="btn btn-primary waves-effect waves-light"
                                   href="{{ route('dashboard') }}">
                                    Back to Dashboard
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-8 col-xl-6">
                    <div>
                        <img src="{{ asset('images/error-img.png') }}" alt="" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
