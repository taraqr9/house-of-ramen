@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    [
                        'label' => 'Logs',
                    ],
                    [
                        'label' => 'Activity',
                    ],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Activity Logs</h5>
                                </div>
                            </div>

                            <form action="{{ route('logs.activity') }}" method="GET">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-2">
                                        <label class="form-label">Log Type</label>
                                        <select name="log_name" class="form-control select2">
                                            <option value="">All Logs</option>

                                            @foreach($logNames as $logName)
                                                <option value="{{ $logName }}" @selected(request('log_name') === $logName)>
                                                    {{ ucwords(str_replace('_', ' ', $logName)) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Event</label>
                                        <select name="event" class="form-control select2">
                                            <option value="">All Events</option>

                                            @foreach($events as $event)
                                                <option value="{{ $event }}" @selected(request('event') === $event)>
                                                    {{ ucwords($event) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Causer</label>
                                        <input type="text"
                                               name="causer"
                                               value="{{ request('causer') }}"
                                               class="form-control"
                                               placeholder="Name, username, email">
                                    </div>

                                    <div class="col-md-2">
                                        <label for="start-date-input" class="form-label">Start Date</label>
                                        <input type="date"
                                               id="start-date-input"
                                               name="start_date"
                                               value="{{ request('start_date') }}"
                                               class="form-control">
                                    </div>

                                    <div class="col-md-2">
                                        <label for="end-date-input" class="form-label">End Date</label>
                                        <input type="date"
                                               id="end-date-input"
                                               name="end_date"
                                               value="{{ request('end_date') }}"
                                               class="form-control">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Description</label>
                                        <input type="text"
                                               name="description"
                                               value="{{ request('description') }}"
                                               class="form-control"
                                               placeholder="Search action">
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('logs.activity') }}" class="btn btn-light waves-effect">
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
                                <table class="table align-middle table-nowrap table-hover dt-responsive nowrap w-100 mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th scope="col" style="width: 60px;">#</th>
                                        <th scope="col">Log Type</th>
                                        <th scope="col">Event</th>
                                        <th scope="col">Description</th>
                                        <th scope="col">Causer</th>
                                        <th scope="col">Model & ID</th>
                                        <th scope="col">IP</th>
                                        <th scope="col">Date Time</th>
                                        <th scope="col" class="text-center" style="width: 100px;">Details</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($logs as $log)
                                        @php
                                            $eventAlertClass = match ($log->event) {
                                                'created', 'create' => 'alert-success',
                                                'updated', 'update', 'edit' => 'alert-warning',
                                                'deleted', 'delete' => 'alert-danger',
                                                'restored', 'restore' => 'alert-info',
                                                default => 'alert-secondary',
                                            };
                                        @endphp

                                        <tr>
                                            <td>
                                                {{ $loop->iteration + ($logs->currentPage() - 1) * $logs->perPage() }}
                                            </td>

                                            <td>
                                                <span class="badge bg-primary">
                                                    {{ $log->log_name }}
                                                </span>
                                            </td>

                                            <td>
                                                @if($log->event)
                                                    <span class="alert {{ $eventAlertClass }} py-1 px-2 mb-0 d-inline-block" role="alert">
                                                        {{ ucwords($log->event) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>

                                            <td>{!! $log->description !!}</td>

                                            <td>
                                                @if($log->causer)
                                                    <button type="button"
                                                            class="btn btn-link p-0 fw-semibold text-primary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#causerModal{{ $log->id }}">
                                                        {{ $log->causer->name ?? 'N/A' }}
                                                    </button>

                                                    <br>

                                                    <small class="text-muted">
                                                        {{ $log->causer->username ?? $log->causer->email ?? '' }}
                                                    </small>
                                                @else
                                                    <span class="text-muted">System</span>
                                                @endif
                                            </td>

                                            <td>
                                                @if($log->subject_type && $log->subject_id)
                                                    <span class="badge bg-secondary">
                                                        {{ class_basename($log->subject_type) }} ( ID: {{ $log->subject_id }} )
                                                    </span>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>

                                            <td>
                                                {{ data_get($log->properties, 'ip', 'N/A') }}
                                            </td>

                                            <td>
                                                {{ $log->created_at ? $log->created_at->timezone('Asia/Dhaka')->format('d M Y h:i A') : '' }}
                                            </td>

                                            <td class="text-center">
                                                <button type="button"
                                                        class="btn btn-sm btn-info waves-effect waves-light"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#activityLogModal{{ $log->id }}">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                No activity logs found.
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>

                                @foreach($logs as $log)
                                    @if($log->causer)
                                        <div id="causerModal{{ $log->id }}"
                                             class="modal fade"
                                             tabindex="-1"
                                             aria-labelledby="causerModalLabel{{ $log->id }}"
                                             aria-hidden="true">
                                            <div class="modal-dialog modal-md modal-dialog-centered">
                                                <div class="modal-content">

                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="causerModalLabel{{ $log->id }}">
                                                            User Information
                                                        </h5>

                                                        <button type="button"
                                                                class="btn-close"
                                                                data-bs-dismiss="modal"
                                                                aria-label="Close"></button>
                                                    </div>

                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <strong>Name:</strong>
                                                            <div>{{ $log->causer->name ?? 'N/A' }}</div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <strong>Username:</strong>
                                                            <div>
                                                                <span class="badge bg-primary">
                                                                    {{ $log->causer->username ?? 'N/A' }}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <strong>Email:</strong>
                                                            <div>{{ $log->causer->email ?? 'N/A' }}</div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <strong>Roles:</strong>
                                                            <div>
                                                                @forelse($log->causer->roles ?? [] as $role)
                                                                    <span class="badge bg-info mb-1">
                                                                    {{ $role->name }}
                                                                </span>
                                                                @empty
                                                                    <span class="text-muted">No role assigned</span>
                                                                @endforelse
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <strong>Created At:</strong>
                                                            <div>
                                                                {{ $log->causer->created_at ? $log->causer->created_at->timezone('Asia/Dhaka')->format('d M Y h:i A') : 'N/A' }}
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <strong>Updated At:</strong>
                                                            <div>
                                                                {{ $log->causer->updated_at ? $log->causer->updated_at->timezone('Asia/Dhaka')->format('d M Y h:i A') : 'N/A' }}
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
                                                            <a href="{{ route('users.edit', $log->causer->id) }}"
                                                               class="btn btn-primary waves-effect waves-light">
                                                                Edit
                                                            </a>
                                                        @endcan
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            @foreach($logs as $log)
                                @php
                                    $attributeChanges = $log->attribute_changes;

                                    if (is_string($attributeChanges)) {
                                        $attributeChanges = json_decode($attributeChanges, true) ?: [];
                                    }

                                    if ($attributeChanges instanceof \Illuminate\Support\Collection) {
                                        $attributeChanges = $attributeChanges->toArray();
                                    }

                                    $oldData = data_get($attributeChanges, 'old', []);
                                    $newData = data_get($attributeChanges, 'attributes', []);

                                    $eventAlertClass = match ($log->event) {
                                        'created', 'create' => 'alert-success',
                                        'updated', 'update', 'edit' => 'alert-warning',
                                        'deleted', 'delete' => 'alert-danger',
                                        'restored', 'restore' => 'alert-info',
                                        default => 'alert-secondary',
                                    };
                                @endphp

                                <div id="activityLogModal{{ $log->id }}"
                                     class="modal fade"
                                     tabindex="-1"
                                     aria-labelledby="activityLogModalLabel{{ $log->id }}"
                                     aria-hidden="true">
                                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                        <div class="modal-content">

                                            <div class="modal-header">
                                                <h5 class="modal-title" id="activityLogModalLabel{{ $log->id }}">
                                                    Activity Log Details
                                                </h5>

                                                <button type="button"
                                                        class="btn-close"
                                                        data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                            </div>

                                            <div class="modal-body">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Log Type:</strong>
                                                        <div>{{ $log->log_name }}</div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <strong>Event:</strong>

                                                        <div class="alert {{ $eventAlertClass }} py-2 px-3 mt-1 mb-0" role="alert">
                                                            {{ $log->event ? ucwords($log->event) : 'N/A' }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Description:</strong>
                                                        <div>{!! $log->description !!}</div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <strong>Date Time:</strong>
                                                        <div>
                                                            {{ $log->created_at ? $log->created_at->timezone('Asia/Dhaka')->format('d M Y h:i A') : '' }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Model Name:</strong>
                                                        <div>{{ $log->subject_type }}</div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <strong>ID:</strong>

                                                        <div>{{ $log->subject_id }}</div>
                                                    </div>
                                                </div>

                                                <hr>

                                                <h6 class="mb-3">Changed / Stored Data</h6>

                                                @if(empty($oldData) && empty($newData))
                                                    <p class="text-muted mb-0">No attribute changes found.</p>
                                                @else
                                                    <div class="row">
                                                        @if(!empty($oldData))
                                                            <div class="col-md-6 mb-3">
                                                                <h6 class="fw-bold text-danger">Old Data</h6>

                                                                <div class="table-responsive">
                                                                    <table class="table table-sm table-bordered mb-0">
                                                                        <thead class="table-light">
                                                                        <tr>
                                                                            <th width="35%">Column</th>
                                                                            <th>Old Value</th>
                                                                        </tr>
                                                                        </thead>

                                                                        <tbody>
                                                                        @foreach($oldData as $column => $value)
                                                                            @php
                                                                                $displayValue = $value;

                                                                                if (!is_array($value) && !is_null($value)) {
                                                                                    try {
                                                                                        if (
                                                                                            str_contains($column, '_at') ||
                                                                                            str_contains($column, 'date') ||
                                                                                            preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)
                                                                                        ) {
                                                                                            $displayValue = \Carbon\Carbon::parse($value)
                                                                                                ->timezone('Asia/Dhaka')
                                                                                                ->format('d M Y h:i A');
                                                                                        }
                                                                                    } catch (\Exception $e) {
                                                                                        $displayValue = $value;
                                                                                    }
                                                                                }
                                                                            @endphp

                                                                            <tr>
                                                                                <td class="fw-semibold">
                                                                                    {{ ucwords(str_replace('_', ' ', $column)) }}
                                                                                </td>

                                                                                <td class="text-break">
                                                                                    @if(is_array($value))
                                                                                        <code>{{ json_encode($value) }}</code>
                                                                                    @elseif(is_null($value))
                                                                                        <span class="text-muted">NULL</span>
                                                                                    @else
                                                                                        {{ $displayValue }}
                                                                                    @endif
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        @endif

                                                        @if(!empty($newData))
                                                            <div class="{{ !empty($oldData) ? 'col-md-6' : 'col-md-12' }} mb-3">
                                                                <h6 class="fw-bold text-success">New Data</h6>

                                                                <div class="table-responsive">
                                                                    <table class="table table-sm table-bordered mb-0">
                                                                        <thead class="table-light">
                                                                        <tr>
                                                                            <th width="35%">Column</th>
                                                                            <th>New Value</th>
                                                                        </tr>
                                                                        </thead>

                                                                        <tbody>
                                                                        @foreach($newData as $column => $value)
                                                                            @php
                                                                                $displayValue = $value;

                                                                                if (!is_array($value) && !is_null($value)) {
                                                                                    try {
                                                                                        if (
                                                                                            str_contains($column, '_at') ||
                                                                                            str_contains($column, 'date') ||
                                                                                            preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)
                                                                                        ) {
                                                                                            $displayValue = \Carbon\Carbon::parse($value)
                                                                                                ->timezone('Asia/Dhaka')
                                                                                                ->format('d M Y h:i A');
                                                                                        }
                                                                                    } catch (\Exception $e) {
                                                                                        $displayValue = $value;
                                                                                    }
                                                                                }
                                                                            @endphp

                                                                            <tr>
                                                                                <td class="fw-semibold">
                                                                                    {{ ucwords(str_replace('_', ' ', $column)) }}
                                                                                </td>

                                                                                <td class="text-break">
                                                                                    @if(is_array($value))
                                                                                        <code>{{ json_encode($value) }}</code>
                                                                                    @elseif(is_null($value))
                                                                                        <span class="text-muted">NULL</span>
                                                                                    @else
                                                                                        {{ $displayValue }}
                                                                                    @endif
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="modal-footer">
                                                <button type="button"
                                                        class="btn btn-secondary waves-effect"
                                                        data-bs-dismiss="modal">
                                                    Close
                                                </button>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <div class="d-flex justify-content-end mt-3">
                                {{ $logs->links('partials.pagination') }}
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
            $('.select2').select2({
                width: '100%',
                allowClear: true
            });

            $('.datepicker').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true
            });
        });
    </script>
@endsection
