@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    [
                        'label' => 'Roles',
                    ],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Role Filters</h5>
                                </div>

                                @can('role-create')
                                    <div class="col-sm-6">
                                        <div class="d-flex justify-content-end">
                                            <a href="{{ route('roles.create') }}" class="btn btn-success">
                                                <i class="mdi mdi-plus"></i> Add Role
                                            </a>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <form action="{{ route('roles.index') }}" method="GET">
                                <div class="row g-2 align-items-end">

                                    <div class="col-md-2">
                                        <label class="form-label">Search</label>
                                        <div class="search-box">
                                            <div class="position-relative">
                                                <input type="text"
                                                       name="keyword"
                                                       value="{{ request('keyword') }}"
                                                       class="form-control"
                                                       id="searchTableList"
                                                       placeholder="Search by role name">

                                                <i class="bx bx-search-alt search-icon"></i>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('roles.index') }}" class="btn btn-light waves-effect">
                                        Reset
                                    </a>

                                    <button type="submit" class="btn btn-primary waves-effect waves-light">
                                        <i class="mdi mdi-magnify me-1"></i>
                                        Search
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="table-responsive">
                                <table class="table align-middle table-hover w-100 mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th scope="col" style="width: 60px;">#</th>
                                        <th scope="col" class="col-md-1">Role Name</th>
                                        <th scope="col">Permissions</th>
                                        <th scope="col" class="text-center" style="width: 200px;">Action</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($roles as $role)
                                        <tr>
                                            <td>
                                                {{ $loop->iteration + ($roles->currentPage() - 1) * $roles->perPage() }}
                                            </td>

                                            <td>
                                                <h5 class="font-size-14 mb-1">
                                                    {{ $role->name }}
                                                </h5>
                                            </td>

                                            <td style="white-space: normal;">
                                                <div class="d-flex flex-wrap gap-1">
                                                    @forelse($role->permissions as $permission)
                                                        <span class="badge bg-info">
                                                            {{ $permission->name }}
                                                        </span>
                                                    @empty
                                                        <span class="text-muted">No permission assigned</span>
                                                    @endforelse
                                                </div>
                                            </td>

                                            <td class="text-center">
                                                @can('role-edit')
                                                    <a href="{{ route('roles.edit', $role->id) }}"
                                                       class="btn btn-sm btn-warning waves-effect waves-light">
                                                        <i class="mdi mdi-pencil"></i>
                                                        Edit
                                                    </a>
                                                @endcan

                                                @can('role-delete')
                                                    @if($role->name !== 'Super Admin')
                                                        <form action="{{ route('roles.destroy', $role->id) }}"
                                                              method="POST"
                                                              class="d-inline delete-role-form">
                                                            @csrf
                                                            @method('DELETE')

                                                            <button type="button"
                                                                    class="btn btn-sm btn-danger waves-effect waves-light delete-role-btn">
                                                                <i class="mdi mdi-trash-can-outline"></i>
                                                                Delete
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">
                                                No roles found.
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                {{ $roles->links('partials.pagination') }}
                            </div>

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
            $('.delete-role-btn').on('click', function () {
                let form = $(this).closest('form');

                Swal.fire({
                    title: 'Are you sure?',
                    text: 'This role will be deleted permanently.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f46a6a',
                    cancelButtonColor: '#74788d',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    </script>
@endsection
