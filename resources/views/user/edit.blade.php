@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    [
                        'label' => 'Users',
                        'url' => route('users.index'),
                    ],
                    [
                        'label' => 'Edit',
                    ],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">

                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Update User</h5>
                                </div>

                                <div class="col-sm-6">
                                    <div class="text-sm-end mt-3 mt-sm-0">
                                        <a href="{{ route('users.index') }}"
                                           class="btn btn-secondary waves-effect waves-light">
                                            <i class="mdi mdi-arrow-left me-1"></i>
                                            Back
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <form action="{{ route('users.update', $user->id) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
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
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                Username <span class="text-danger">*</span>
                                            </label>

                                            <input type="text"
                                                   name="username"
                                                   value="{{ old('username', $user->username) }}"
                                                   class="form-control @error('username') is-invalid @enderror"
                                                   placeholder="Enter username">

                                            @error('username')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                Email <span class="text-danger">*</span>
                                            </label>

                                            <input type="email"
                                                   name="email"
                                                   value="{{ old('email', $user->email) }}"
                                                   class="form-control @error('email') is-invalid @enderror"
                                                   placeholder="Enter email address">

                                            @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>

                                            <select name="role"
                                                    class="form-control select2 @error('role') is-invalid @enderror">
                                                <option value="">Select Role</option>

                                                @foreach($roles as $role)
                                                    <option value="{{ $role->name }}"
                                                        @selected(old('role', $userRole) === $role->name)>
                                                        {{ $role->name }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            @error('role')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                Password
                                            </label>

                                            <input type="password"
                                                   name="password"
                                                   class="form-control @error('password') is-invalid @enderror"
                                                   placeholder="Leave empty to keep current password">

                                            @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror

                                            <small class="text-muted">
                                                Leave empty if you do not want to change password.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                Confirm Password
                                            </label>

                                            <input type="password"
                                                   name="password_confirmation"
                                                   class="form-control"
                                                   placeholder="Confirm new password">
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                Remarks
                                            </label>

                                            <textarea name="remarks"
                                                      class="form-control @error('remarks') is-invalid @enderror"
                                                      rows="3"
                                                      placeholder="Enter user remarks">{{ old('remarks', $user->remarks) }}</textarea>

                                            @error('remarks')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6 mt-3">
                                        <label>Status</label>

                                        <div class="form-check form-switch mt-2">
                                            <input type="checkbox"
                                                   name="is_active"
                                                   value="1"
                                                   class="form-check-input"
                                                   id="isActive"
                                                {{ old('is_active', $user->is_active->value) ? 'checked' : '' }}>

                                            <label class="form-check-label" for="isActive">
                                                Active
                                            </label>
                                        </div>

                                        @error('is_active')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('users.index') }}"
                                           class="btn btn-light waves-effect">
                                            Cancel
                                        </a>

                                        <button type="submit"
                                                class="btn btn-primary waves-effect waves-light">
                                            <i class="mdi mdi-content-save-outline me-1"></i>
                                            Update
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
            $('.select2').select2({
                width: '100%',
                allowClear: true
            });
        });
    </script>
@endsection
