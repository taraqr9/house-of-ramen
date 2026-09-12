@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'My Profile'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3">
                                <div class="col-12">
                                    <h5 class="card-title mb-1">My Profile</h5>
                                </div>
                            </div>

                            <form action="{{ route('profile.update') }}"
                                  method="POST"
                                  id="profileForm">
                                @csrf
                                @method('PUT')

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">
                                            Name <span class="text-danger">*</span>
                                        </label>

                                        <input type="text"
                                               name="name"
                                               value="{{ old('name', $user->name) }}"
                                               class="form-control @error('name') is-invalid @enderror"
                                               placeholder="Enter full name">

                                        @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Username</label>

                                        <input type="text"
                                               value="{{ $user->username }}"
                                               class="form-control"
                                               readonly
                                               disabled>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Email</label>

                                        <input type="email"
                                               value="{{ $user->email }}"
                                               class="form-control"
                                               readonly
                                               disabled>
                                    </div>
                                </div>

                                <hr>

                                <div class="alert alert-info py-2 mb-3">
                                    Leave the password fields empty if you do not want to change your password.
                                </div>

                                <div class="alert alert-warning py-2 mb-3">
                                    <h6 class="alert-heading mb-1">
                                        <i class="mdi mdi-shield-key-outline me-1"></i>
                                        Password Rules
                                    </h6>

                                    <ul class="mb-0 ps-3 small password-rules" style="line-height: 1.45;">
                                        <li id="ruleLength">Password must be <strong>8 to 15 characters</strong>.</li>
                                        <li id="ruleUppercase">Must include at least one uppercase letter.</li>
                                        <li id="ruleLowercase">Must include at least one lowercase letter.</li>
                                        <li id="ruleNumber">Must include at least one number.</li>
                                        <li id="ruleSpecial">Must include at least one special character.</li>
                                        <li id="ruleConfirm">New password and confirm password must match.</li>
                                    </ul>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">New Password</label>

                                        <input type="password"
                                               name="password"
                                               id="password"
                                               class="form-control @error('password') is-invalid @enderror"
                                               placeholder="Enter new password">

                                        @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="password_confirmation" class="form-label">Confirm New Password</label>

                                        <input type="password"
                                               name="password_confirmation"
                                               id="password_confirmation"
                                               class="form-control"
                                               placeholder="Confirm new password">
                                    </div>
                                </div>

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="submit"
                                                id="profileSubmitBtn"
                                                class="btn btn-primary waves-effect waves-light">
                                            <i class="mdi mdi-content-save-outline me-1"></i>
                                            Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@section('JScript')
    <script>
        $(document).ready(function () {
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('password_confirmation');
            const submitButton = document.getElementById('profileSubmitBtn');

            function setRuleStatus(id, isValid) {
                const rule = document.getElementById(id);

                if (!rule) {
                    return;
                }

                if (isValid) {
                    rule.style.color = '#198754';
                    rule.style.fontWeight = '600';
                } else {
                    rule.style.color = '';
                    rule.style.fontWeight = '400';
                }
            }

            function validatePasswordRules() {
                const password = passwordInput ? passwordInput.value : '';
                const confirmPassword = confirmInput ? confirmInput.value : '';

                if (password.length === 0 && confirmPassword.length === 0) {
                    ['ruleLength', 'ruleUppercase', 'ruleLowercase', 'ruleNumber', 'ruleSpecial', 'ruleConfirm']
                        .forEach(function (id) {
                            setRuleStatus(id, false);
                        });

                    if (submitButton) {
                        submitButton.disabled = false;
                    }

                    return;
                }

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
