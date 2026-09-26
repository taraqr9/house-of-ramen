@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    [
                        'label' => 'Roles',
                        'url' => route('roles.index'),
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
                                    <h5 class="card-title mb-1">Update Role</h5>
                                </div>

                                <div class="col-sm-6">
                                    <div class="text-sm-end mt-3 mt-sm-0">
                                        <a href="{{ route('roles.index') }}"
                                           class="btn btn-secondary waves-effect waves-light">
                                            <i class="mdi mdi-arrow-left me-1"></i>
                                            Back
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <form action="{{ route('roles.update', $role->id) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                Role Name <span class="text-danger">*</span>
                                            </label>

                                            <input type="text"
                                                   name="name"
                                                   value="{{ old('name', $role->name) }}"
                                                   class="form-control @error('name') is-invalid @enderror"
                                                   placeholder="Example: Super Admin, Admin, HR Manager">

                                            @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                @include('roles._permissions', ['selectedPermissions' => old('permissions', $rolePermissions)])

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('roles.index') }}"
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

    <div id="createPermissionModal"
         class="modal fade"
         tabindex="-1"
         aria-labelledby="createPermissionModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">

                <form action="{{ route('permissions.store') }}" method="POST">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title" id="createPermissionModalLabel">
                            Create New Permission
                        </h5>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Model Name</label>

                            <select name="module_name"
                                    id="permission_module_name"
                                    class="form-control select2"
                                    required>
                                <option value="">Select Model</option>

                                @foreach($models as $model)
                                    <option value="{{ $model['value'] }}">
                                        {{ $model['label'] }}
                                    </option>
                                @endforeach
                            </select>

                            <small class="text-muted">
                                Search and select the model/module for which permissions will be created.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Actions</label>

                            <div class="row">
                                @php
                                    $defaultActions = ['view', 'create', 'edit', 'delete'];
                                @endphp

                                @foreach($defaultActions as $action)
                                    <div class="col-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="actions[]"
                                                   value="{{ $action }}"
                                                   id="edit_action_{{ $action }}"
                                                   checked>

                                            <label class="form-check-label text-capitalize"
                                                   for="edit_action_{{ $action }}">
                                                {{ $action }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Custom Action</label>
                            <input type="text"
                                   name="actions[]"
                                   class="form-control"
                                   placeholder="Example: approve, export, print">

                            <small class="text-muted">
                                Optional. Leave empty if not needed.
                            </small>
                        </div>

                        <div class="alert alert-info mb-0">
                            <i class="mdi mdi-alert-circle-outline me-1"></i>
                            Example: Module <strong>report</strong> with action <strong>view</strong>
                            creates <strong>report-view</strong>.
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button"
                                class="btn btn-secondary waves-effect"
                                data-bs-dismiss="modal">
                            Close
                        </button>

                        <button type="submit"
                                class="btn btn-primary waves-effect waves-light">
                            Save Permission
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>
@endsection

@section('JScript')
    <script>
        $(document).ready(function () {
            $('#permission_module_name').select2({
                dropdownParent: $('#createPermissionModal'),
                width: '100%',
                placeholder: 'Select Model',
                allowClear: true,
                minimumResultsForSearch: 0
            });
        });
    </script>

    @include('roles._permissions-script')
@endsection
