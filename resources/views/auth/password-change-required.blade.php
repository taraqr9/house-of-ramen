@extends('layout.auth')

@section('AuthCSSheet')
    <style>
        .password-rules li {
            color: #000000;
            transition: all 0.2s ease-in-out;
        }

        .password-rule-valid {
            color: #198754 !important;
            font-weight: 600;
        }

        .password-rule-invalid {
            color: #000000 !important;
            font-weight: 400;
        }

        .password-rule-valid::marker {
            color: #198754;
        }
    </style>
@endsection

@section('AuthContent')

    <div class="account-pages py-3">
        <div class="container">
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-md-8 col-lg-6 col-xl-5">

                    <div class="card overflow-hidden mb-2">

                        <div class="bg-primary-subtle">
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <div class="text-primary p-3">
                                        <h5 class="text-primary mb-1">Change Your Password</h5>
                                        <p class="mb-0 small">
                                            You must set a new password before continuing.
                                        </p>
                                    </div>
                                </div>

                                <div class="col-4 align-self-end">
                                    <img src="{{ asset('images/logo.svg') }}"
                                         alt=""
                                         class="img-fluid">
                                </div>
                            </div>
                        </div>

                        <div class="card-body pt-0">
                            <div class="avatar-md profile-user-wid mb-2">
                                <span class="avatar-title rounded-circle">
                                    <img src="{{ asset('images/logo.svg') }}"
                                         alt=""
                                         class="rounded-circle"
                                         height="34">
                                </span>
                            </div>

                            <div class="p-2">

                                @if(session('error'))
                                    <div class="alert alert-danger text-center py-2 mb-2" role="alert">
                                        {{ session('error') }}
                                    </div>
                                @endif

                                @if(session('success'))
                                    <div class="alert alert-success text-center py-2 mb-2" role="alert">
                                        {{ session('success') }}
                                    </div>
                                @endif

                                <div class="alert alert-info text-center py-2 mb-2" role="alert">
                                    Enter your current password, then set your new password.
                                </div>

                                    <div class="alert alert-warning py-2 mb-3" role="alert">
                                        <h6 class="alert-heading mb-1">
                                            <i class="mdi mdi-shield-key-outline me-1"></i>
                                            Password Rules
                                        </h6>

                                        <ul class="mb-0 ps-3 small password-rules" style="line-height: 1.45;">
                                            <li id="ruleLength">
                                                Password must be <strong>8 to 15 characters</strong>.
                                            </li>

                                            <li id="ruleUppercase">
                                                Must include at least one uppercase letter.
                                            </li>

                                            <li id="ruleLowercase">
                                                Must include at least one lowercase letter.
                                            </li>

                                            <li id="ruleNumber">
                                                Must include at least one number.
                                            </li>

                                            <li id="ruleSpecial">
                                                Must include at least one special character.
                                            </li>

                                            <li id="ruleConfirm">
                                                New password and confirm password must match.
                                            </li>
                                        </ul>
                                    </div>

                                <form method="POST" action="{{ route('logout') }}" class="m-0 mb-2 text-end">
                                    @csrf

                                    <button type="submit" class="btn btn-link text-danger p-0 fw-medium">
                                        Logout
                                    </button>
                                </form>

                                <form class="form-horizontal"
                                      action="{{ route('password.change.update') }}"
                                      method="POST">
                                    @csrf

                                    <div class="mb-2">
                                        <label for="current_password" class="form-label mb-1">
                                            Current Password <span class="text-danger">*</span>
                                        </label>

                                        <input type="password"
                                               name="current_password"
                                               id="current_password"
                                               class="form-control @error('current_password') is-invalid @enderror"
                                               placeholder="Enter current password">

                                        @error('current_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-2">
                                        <label for="password" class="form-label mb-1">
                                            New Password <span class="text-danger">*</span>
                                        </label>

                                        <input type="password"
                                               name="password"
                                               id="password"
                                               class="form-control @error('password') is-invalid @enderror"
                                               placeholder="Enter new password">

                                        @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="password_confirmation" class="form-label mb-1">
                                            Confirm New Password <span class="text-danger">*</span>
                                        </label>

                                        <input type="password"
                                               name="password_confirmation"
                                               id="password_confirmation"
                                               class="form-control"
                                               placeholder="Confirm new password">
                                    </div>

                                    <div class="d-flex justify-content-end align-items-center">
                                        <button class="btn btn-primary w-md waves-effect waves-light"
                                                type="submit"
                                                id="changePasswordBtn"
                                                disabled>
                                            Change Password
                                        </button>
                                    </div>

                                </form>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

@endsection

@section('AuthJScript')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('password_confirmation');
            const submitButton = document.getElementById('changePasswordBtn');

            function setRuleStatus(id, isValid) {
                const rule = document.getElementById(id);

                if (!rule) {
                    return;
                }

                if (isValid) {
                    rule.style.color = '#198754';
                    rule.style.fontWeight = '600';
                } else {
                    rule.style.color = '#000000';
                    rule.style.fontWeight = '400';
                }
            }

            function validatePasswordRules() {
                const password = passwordInput ? passwordInput.value : '';
                const confirmPassword = confirmInput ? confirmInput.value : '';

                const hasValidLength = password.length >= 8 && password.length <= 15;
                const hasUppercase = /[A-Z]/.test(password);
                const hasLowercase = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                const hasSpecial = /[^A-Za-z0-9]/.test(password);
                const hasConfirmMatch = password.length > 0
                    && confirmPassword.length > 0
                    && password === confirmPassword;

                setRuleStatus('ruleLength', hasValidLength);
                setRuleStatus('ruleUppercase', hasUppercase);
                setRuleStatus('ruleLowercase', hasLowercase);
                setRuleStatus('ruleNumber', hasNumber);
                setRuleStatus('ruleSpecial', hasSpecial);
                setRuleStatus('ruleConfirm', hasConfirmMatch);

                const allRulesValid =
                    hasValidLength &&
                    hasUppercase &&
                    hasLowercase &&
                    hasNumber &&
                    hasSpecial &&
                    hasConfirmMatch;

                if (submitButton) {
                    submitButton.disabled = !allRulesValid;
                }
            }

            if (passwordInput) {
                passwordInput.addEventListener('input', validatePasswordRules);
                passwordInput.addEventListener('keyup', validatePasswordRules);
                passwordInput.addEventListener('change', validatePasswordRules);
            }

            if (confirmInput) {
                confirmInput.addEventListener('input', validatePasswordRules);
                confirmInput.addEventListener('keyup', validatePasswordRules);
                confirmInput.addEventListener('change', validatePasswordRules);
            }

            validatePasswordRules();
        });
    </script>
@endsection
