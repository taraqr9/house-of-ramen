@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    [
                        'label' => 'Users',
                    ],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">User Filters</h5>
                                </div>
                                @can('user-create')
                                    <div class="col-sm-6">
                                        <div class="d-flex justify-content-end">
                                            <a href="{{ route('users.create') }}" class="btn btn-success">
                                                <i class="mdi mdi-plus"></i> Add User
                                            </a>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <form action="{{ route('users.index') }}" method="GET">
                                <div class="row g-2 align-items-end">

                                    <div class="col-md-2">
                                        <label class="form-label">Role</label>
                                        <select name="role" class="form-control select2">
                                            <option value="">All Roles</option>

                                            @foreach($roles as $role)
                                                <option
                                                    value="{{ $role->name }}" @selected(request('role') === $role->name)>
                                                    {{ $role->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select name="is_active" class="form-control select2">
                                            <option value="">All Status</option>

                                            @foreach($statuses as $value => $label)
                                                <option
                                                    value="{{ $value }}" @selected(request('is_active') === $value)>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Search</label>
                                        <div class="search-box">
                                            <div class="position-relative">
                                                <input type="text"
                                                       name="keyword"
                                                       value="{{ request('keyword') }}"
                                                       class="form-control"
                                                       id="searchTableList"
                                                       placeholder="Search by name, username, email...">

                                                <i class="bx bx-search-alt search-icon"></i>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('users.index') }}" class="btn btn-light waves-effect">
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
                                <table
                                    class="table align-middle table-nowrap table-hover dt-responsive nowrap w-100 mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th scope="col" style="width: 60px;">#</th>
                                        <th scope="col">Name</th>
                                        <th scope="col">Username</th>
                                        <th scope="col">Email</th>
                                        <th scope="col">Role</th>
                                        <th scope="col">Status</th>
                                        <th scope="col" class="text-center" style="width: 200px;">Action</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($users as $user)
                                        <tr>
                                            <td>
                                                {{ $loop->iteration + ($users->currentPage() - 1) * $users->perPage() }}
                                            </td>

                                            <td>
                                                <h5 class="font-size-14 mb-1">
                                                    {{ $user->name }}
                                                </h5>
                                            </td>

                                            <td>
                                                <span class="badge bg-primary">
                                                    {{ $user->username }}
                                                </span>
                                            </td>

                                            <td>
                                                {{ $user->email }}
                                            </td>

                                            <td>
                                                @forelse($user->roles as $role)
                                                    <span class="badge bg-info mb-1">
                                                        {{ $role->name }}
                                                    </span>
                                                @empty
                                                    <span class="text-muted">No role assigned</span>
                                                @endforelse
                                            </td>

                                            <td>
                                                <span class="badge {{ $user->is_active->badgeClass() }}">
                                                    {{ $user->is_active->label() }}
                                                </span>
                                            </td>

                                            <td class="text-center">
                                                <button type="button"
                                                        class="btn btn-sm btn-info waves-effect waves-light"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#userViewModal{{ $user->id }}">
                                                    <i class="mdi mdi-eye-outline"></i>
                                                    View
                                                </button>

                                                @can('user-edit')
                                                    <a href="{{ route('users.edit', $user->id) }}"
                                                       class="btn btn-sm btn-warning waves-effect waves-light">
                                                        <i class="mdi mdi-pencil"></i>
                                                        Edit
                                                    </a>
                                                @endcan

                                                @can('user-impersonate')
                                                    @if(auth()->id() !== $user->id)
                                                        <form action="{{ route('users.impersonate', $user->id) }}"
                                                              method="POST"
                                                              class="d-inline">
                                                            @csrf

                                                            <button type="submit"
                                                                    class="btn btn-sm btn-info waves-effect waves-light">
                                                                <i class="mdi mdi-account-switch-outline"></i>
                                                                Impersonate
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endcan

                                                @can('user-delete')
                                                    @if(auth()->id() !== $user->id)
                                                        <form action="{{ route('users.destroy', $user->id) }}"
                                                              method="POST"
                                                              class="d-inline delete-user-form">
                                                            @csrf
                                                            @method('DELETE')

                                                            <button type="button"
                                                                    class="btn btn-sm btn-danger waves-effect waves-light delete-user-btn">
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
                                            <td colspan="6" class="text-center text-muted">
                                                No users found.
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>

                                @foreach($users as $user)
                                    <div id="userViewModal{{ $user->id }}"
                                         class="modal fade"
                                         tabindex="-1"
                                         aria-labelledby="userViewModalLabel{{ $user->id }}"
                                         aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                            <div class="modal-content">

                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="userViewModalLabel{{ $user->id }}">
                                                        User Details
                                                    </h5>

                                                    <button type="button"
                                                            class="btn-close"
                                                            data-bs-dismiss="modal"
                                                            aria-label="Close"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Name:</strong>
                                                            <div>{{ $user->name }}</div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <strong>Username:</strong>
                                                            <div>
                                                                <span class="badge bg-primary">
                                                                    {{ $user->username }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Email:</strong>
                                                            <div>{{ $user->email }}</div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <strong>Role:</strong>
                                                            <div>
                                                                @forelse($user->roles as $role)
                                                                    <span class="badge bg-info mb-1">
                                                                        {{ $role->name }}
                                                                    </span>
                                                                @empty
                                                                    <span class="text-muted">No role assigned</span>
                                                                @endforelse
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <hr>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Email Verified At:</strong>
                                                            <div>
                                                                {{ $user->email_verified_at ? $user->email_verified_at->timezone('Asia/Dhaka')->format('d M Y h:i A') : 'N/A' }}
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <strong>Created At:</strong>
                                                            <div>
                                                                {{ $user->created_at ? $user->created_at->timezone('Asia/Dhaka')->format('d M Y h:i A') : 'N/A' }}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Updated At:</strong>
                                                            <div>
                                                                {{ $user->updated_at ? $user->updated_at->timezone('Asia/Dhaka')->format('d M Y h:i A') : 'N/A' }}
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <strong>Deleted At:</strong>
                                                            <div>
                                                                {{ $user->deleted_at ? $user->deleted_at->timezone('Asia/Dhaka')->format('d M Y h:i A') : 'N/A' }}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <hr>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <strong>Created By:</strong>
                                                            <div>{{ $user->created_by ?? 'N/A' }}</div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <strong>Updated By:</strong>
                                                            <div>{{ $user->updated_by ?? 'N/A' }}</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button"
                                                            class="btn btn-secondary waves-effect"
                                                            data-bs-dismiss="modal">
                                                        Close
                                                    </button>

                                                    @can('user-edit')
                                                        <a href="{{ route('users.edit', $user->id) }}"
                                                           class="btn btn-primary waves-effect waves-light">
                                                            Edit
                                                        </a>
                                                    @endcan
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                {{ $users->links('partials.pagination') }}
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
            $('.delete-user-btn').on('click', function () {
                let form = $(this).closest('form');

                Swal.fire({
                    title: 'Are you sure?',
                    text: 'This user will be deleted permanently.',
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

            $('#searchTableList').on('keypress', function (e) {
                if (e.which === 13) {
                    $(this).closest('form').submit();
                }
            });
        });
    </script>
@endsection
