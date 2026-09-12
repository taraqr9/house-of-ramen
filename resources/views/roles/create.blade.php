@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'title' => 'Create Role',
                'items' => [
                    [
                        'label' => 'Roles',
                        'url' => route('roles.index'),
                    ],
                    [
                        'label' => 'Create',
                    ],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">

                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Add New Role</h5>
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

                            <form action="{{ route('roles.store') }}" method="POST">
                                @csrf

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                Role Name <span class="text-danger">*</span>
                                            </label>

                                            <input type="text"
                                                   name="name"
                                                   value="{{ old('name') }}"
                                                   class="form-control @error('name') is-invalid @enderror"
                                                   placeholder="Example: Super Admin, Admin, HR Manager">

                                            @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
                                        <div>
                                            <label class="form-label mb-1 fw-bold">Permissions</label>
                                        </div>

                                        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-2">
                                            <button type="button"
                                                    class="btn btn-sm btn-primary waves-effect waves-light"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#createPermissionModal">
                                                <i class="mdi mdi-plus-circle-outline me-1"></i>
                                                Create Permission
                                            </button>

                                            <div class="form-check d-inline-flex align-items-center gap-2 bg-primary-subtle rounded px-3 py-2 shadow-sm">
                                                <input class="form-check-input m-0"
                                                       type="checkbox"
                                                       id="select_all_permissions">

                                                <label class="form-check-label fw-bold text-primary mb-0"
                                                       for="select_all_permissions">
                                                    Select All
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    @php
                                        $groupedPermissions = $permissions->groupBy(function ($permission) {
                                            return explode('-', $permission->name)[0];
                                        });
                                    @endphp

                                    <div class="row">
                                        @foreach($groupedPermissions as $sectionName => $sectionPermissions)
                                            <div class="col-12 mb-3">
                                                <div class="border rounded shadow-sm">
                                                    <div class="bg-light px-3 py-2 border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                                                        <h6 class="mb-0 fw-bold text-capitalize">
                                                            {{ str_replace('_', ' ', $sectionName) }}
                                                        </h6>

                                                        <div class="form-check d-inline-flex align-items-center gap-2">
                                                            <input class="form-check-input m-0 section-permission-check"
                                                                   type="checkbox"
                                                                   data-section="{{ $sectionName }}"
                                                                   id="section_{{ $sectionName }}">

                                                            <label class="form-check-label mb-0 small fw-semibold"
                                                                   for="section_{{ $sectionName }}">
                                                                Select All
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <div class="p-3">
                                                        <div class="row">
                                                            @foreach($sectionPermissions as $permission)
                                                                @php
                                                                    $permissionParts = explode('-', $permission->name);
                                                                    $actionName = $permissionParts[1] ?? $permission->name;
                                                                @endphp

                                                                <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-2">
                                                                    <div class="form-check">
                                                                        <input
                                                                            class="form-check-input permission-checkbox section-{{ $sectionName }}"
                                                                            type="checkbox"
                                                                            name="permissions[]"
                                                                            value="{{ $permission->name }}"
                                                                            id="permission_{{ $permission->id }}"
                                                                            data-section="{{ $sectionName }}"
                                                                            @checked(in_array($permission->name, old('permissions', [])))>

                                                                        <label class="form-check-label"
                                                                               for="permission_{{ $permission->id }}">
                                                                            {{ ucwords(str_replace('_', ' ', $actionName)) }}
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    @error('permissions')
                                    <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('roles.index') }}"
                                           class="btn btn-light waves-effect">
                                            Cancel
                                        </a>

                                        <button type="submit"
                                                class="btn btn-primary waves-effect waves-light">
                                            <i class="mdi mdi-content-save-outline me-1"></i>
                                            Save
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
                                                   id="action_{{ $action }}"
                                                   checked>

                                            <label class="form-check-label text-capitalize"
                                                   for="action_{{ $action }}">
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

            function updateMainSelectAll() {
                let totalPermissions = $('.permission-checkbox').length;
                let checkedPermissions = $('.permission-checkbox:checked').length;

                $('#select_all_permissions').prop(
                    'checked',
                    totalPermissions > 0 && totalPermissions === checkedPermissions
                );
            }

            function updateSectionSelectAll(sectionName) {
                let sectionCheckboxes = $('.section-' + sectionName);
                let totalSectionPermissions = sectionCheckboxes.length;
                let checkedSectionPermissions = sectionCheckboxes.filter(':checked').length;

                $('#section_' + sectionName).prop(
                    'checked',
                    totalSectionPermissions > 0 && totalSectionPermissions === checkedSectionPermissions
                );
            }

            $('#select_all_permissions').on('change', function () {
                let isChecked = $(this).is(':checked');

                $('.permission-checkbox').prop('checked', isChecked);
                $('.section-permission-check').prop('checked', isChecked);
            });

            $('.section-permission-check').on('change', function () {
                let sectionName = $(this).data('section');
                let isChecked = $(this).is(':checked');

                $('.section-' + sectionName).prop('checked', isChecked);

                updateMainSelectAll();
            });

            $('.permission-checkbox').on('change', function () {
                let sectionName = $(this).data('section');

                updateSectionSelectAll(sectionName);
                updateMainSelectAll();
            });

            $('.section-permission-check').each(function () {
                let sectionName = $(this).data('section');
                updateSectionSelectAll(sectionName);
            });

            updateMainSelectAll();
        });
    </script>
@endsection
