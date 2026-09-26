{{--
    Permission picker shared by roles/create and roles/edit.
    Expects: $permissions (all Permission models), $selectedPermissions (names).

    Resources ("restaurant_menu_item" from "restaurant_menu_item-edit") are
    grouped into the same modules as the sidebar. On phones each resource
    group is a collapsible panel; from tablet up they stay expanded like
    before. Field names (permissions[]) and the element ids/classes used by
    roles/_permissions-script are unchanged.
--}}
@php
    $moduleOf = function (string $resource): string {
        return match (true) {
            in_array($resource, ['dashboard', 'user', 'role', 'menu', 'activity_log', 'error_log', 'notification'], true) => 'System Administration',
            $resource === 'restaurant' || str_starts_with($resource, 'restaurant_') => 'Restaurant Settings',
            in_array($resource, ['dining_table', 'order', 'order_item', 'kitchen', 'serving', 'billing', 'payment', 'pos_report'], true) => 'POS Operations',
            default => 'Other',
        };
    };

    $groupedPermissions = $permissions->groupBy(fn ($permission) => explode('-', $permission->name)[0]);

    $modules = collect(['System Administration', 'Restaurant Settings', 'POS Operations', 'Other'])
        ->mapWithKeys(fn ($module) => [$module => $groupedPermissions->filter(fn ($perms, $resource) => $moduleOf($resource) === $module)])
        ->filter(fn ($resources) => $resources->isNotEmpty());
@endphp

<div class="mb-3">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2 mb-3">
        <label class="form-label mb-0 fw-bold">
            Permissions
            <span class="badge bg-primary-subtle text-primary ms-1"><span id="permission_checked_count">0</span> / {{ $permissions->count() }}</span>
        </label>

        <div class="d-flex flex-wrap align-items-center gap-2 role-perm-toolbar">
            <button type="button"
                    class="btn btn-sm btn-primary waves-effect waves-light"
                    data-bs-toggle="modal"
                    data-bs-target="#createPermissionModal">
                <i class="mdi mdi-plus-circle-outline me-1"></i>
                Create Permission
            </button>

            <button type="button" class="btn btn-sm btn-light d-md-none" id="permission_expand_all">
                <i class="mdi mdi-unfold-more-horizontal me-1"></i> Expand all
            </button>

            <div class="form-check d-inline-flex align-items-center gap-2 bg-primary-subtle rounded px-3 py-2 shadow-sm mb-0">
                <input class="form-check-input m-0" type="checkbox" id="select_all_permissions">
                <label class="form-check-label fw-bold text-primary mb-0" for="select_all_permissions">
                    Select All
                </label>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <div class="input-group">
            <span class="input-group-text bg-white">
                <i class="mdi mdi-magnify"></i>
            </span>
            <input type="text"
                   id="permission_search"
                   class="form-control"
                   placeholder="Search permissions (e.g. order, payment, view)...">
        </div>
        <small class="text-muted" id="permission_search_empty" style="display: none;">
            No permission groups match your search.
        </small>
    </div>

    @foreach($modules as $moduleName => $resources)
        @php $moduleKey = \Illuminate\Support\Str::slug($moduleName, '_'); @endphp

        <div class="permission-module mb-4" data-module="{{ $moduleKey }}">
            <div class="d-flex justify-content-between align-items-center gap-2 border-bottom pb-2 mb-2">
                <h6 class="mb-0 fw-bold text-uppercase text-muted small">
                    {{ $moduleName }}
                    <span class="badge bg-light text-muted ms-1 module-count" data-module="{{ $moduleKey }}"></span>
                </h6>

                <div class="form-check d-inline-flex align-items-center gap-2 mb-0">
                    <input class="form-check-input m-0 module-permission-check"
                           type="checkbox"
                           data-module="{{ $moduleKey }}"
                           id="module_{{ $moduleKey }}">
                    <label class="form-check-label mb-0 small fw-semibold" for="module_{{ $moduleKey }}">
                        Select module
                    </label>
                </div>
            </div>

            <div class="row">
                @foreach($resources as $sectionName => $sectionPermissions)
                    <div class="col-12 mb-2 permission-group"
                         data-module="{{ $moduleKey }}"
                         data-search="{{ strtolower($moduleName) }} {{ strtolower(str_replace('_', ' ', $sectionName)) }} {{ strtolower($sectionPermissions->pluck('name')->implode(' ')) }}">
                        <div class="border rounded shadow-sm">
                            <div class="bg-light px-3 py-2 d-flex justify-content-between align-items-center gap-2 permission-group-head">
                                {{-- Phones: the title toggles the group open/closed. --}}
                                <button type="button"
                                        class="btn btn-link text-body text-decoration-none p-0 text-start flex-grow-1 permission-group-toggle"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#perm_body_{{ $sectionName }}"
                                        aria-expanded="false"
                                        aria-controls="perm_body_{{ $sectionName }}">
                                    <span class="fw-bold text-capitalize">{{ str_replace('_', ' ', $sectionName) }}</span>
                                    <span class="badge bg-white text-muted border ms-1 section-count" data-section="{{ $sectionName }}"></span>
                                    <i class="mdi mdi-chevron-down d-md-none permission-group-caret"></i>
                                </button>

                                <div class="form-check d-inline-flex align-items-center gap-2 mb-0 flex-shrink-0">
                                    <input class="form-check-input m-0 section-permission-check"
                                           type="checkbox"
                                           data-section="{{ $sectionName }}"
                                           id="section_{{ $sectionName }}">
                                    <label class="form-check-label mb-0 small fw-semibold" for="section_{{ $sectionName }}">
                                        All
                                    </label>
                                </div>
                            </div>

                            {{-- collapse on phones, always shown from md up. --}}
                            <div class="collapse d-md-block border-top permission-group-body" id="perm_body_{{ $sectionName }}">
                                <div class="p-3 pb-1">
                                    <div class="row">
                                        @foreach($sectionPermissions as $permission)
                                            @php
                                                $permissionParts = explode('-', $permission->name);
                                                $actionName = $permissionParts[1] ?? $permission->name;
                                            @endphp

                                            <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input permission-checkbox section-{{ $sectionName }}"
                                                           type="checkbox"
                                                           name="permissions[]"
                                                           value="{{ $permission->name }}"
                                                           id="permission_{{ $permission->id }}"
                                                           data-section="{{ $sectionName }}"
                                                           data-module="{{ $moduleKey }}"
                                                           @checked(in_array($permission->name, $selectedPermissions))>
                                                    <label class="form-check-label" for="permission_{{ $permission->id }}">
                                                        {{ ucwords(str_replace('_', ' ', $actionName)) }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @error('permissions')
    <div class="text-danger small">{{ $message }}</div>
    @enderror
</div>
