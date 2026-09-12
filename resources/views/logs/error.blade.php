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
                        'label' => 'Error',
                    ],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Error Logs</h5>
                                </div>
                            </div>

                            <form action="{{ route('logs.error') }}" method="GET">
                                <div class="row g-2 align-items-end">

                                    <div class="col-md-2">
                                        <label class="form-label">Log File</label>
                                        <select name="file_name" class="form-control select2">
                                            <option value="">All Files</option>

                                            @foreach($fileNames as $fileName)
                                                <option value="{{ $fileName }}" @selected(request('file_name') === $fileName)>
                                                    {{ $fileName }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Exception</label>
                                        <input type="text"
                                               name="exception"
                                               value="{{ request('exception') }}"
                                               class="form-control"
                                               placeholder="Exception class">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Message</label>
                                        <input type="text"
                                               name="message"
                                               value="{{ request('message') }}"
                                               class="form-control"
                                               placeholder="Search message">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">URL</label>
                                        <input type="text"
                                               name="url"
                                               value="{{ request('url') }}"
                                               class="form-control"
                                               placeholder="Search URL">
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

                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('logs.error') }}" class="btn btn-light waves-effect">
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
                                        <th scope="col">Date Time</th>
                                        <th scope="col">Line</th>
                                        <th scope="col">Message</th>
                                        <th scope="col" class="text-center" style="width: 100px;">Details</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($logs as $log)
                                        <tr>
                                            <td>
                                                {{ $loop->iteration + ($logs->currentPage() - 1) * $logs->perPage() }}
                                            </td>

                                            <td>
                                                {{ $log->date_time ? \Carbon\Carbon::parse($log->date_time)->timezone('Asia/Dhaka')->format('d M Y h:i A') : 'N/A' }}
                                            </td>

                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    {{ $log->line }}
                                                </span>
                                            </td>

                                            <td class="text-break">
                                                {{ \Illuminate\Support\Str::limit($log->message, 150) }}
                                            </td>

                                            <td class="text-center">
                                                <button type="button"
                                                        class="btn btn-sm btn-info waves-effect waves-light"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#errorLogModal{{ $loop->index }}">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                No error logs found.
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @foreach($logs as $log)
                                @if($log->user_id)
                                    <div id="userModal{{ $loop->index }}"
                                         class="modal fade"
                                         tabindex="-1"
                                         aria-labelledby="userModalLabel{{ $loop->index }}"
                                         aria-hidden="true">
                                        <div class="modal-dialog modal-md modal-dialog-centered">
                                            <div class="modal-content">

                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="userModalLabel{{ $loop->index }}">
                                                        User Information
                                                    </h5>

                                                    <button type="button"
                                                            class="btn-close"
                                                            data-bs-dismiss="modal"
                                                            aria-label="Close"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <strong>User ID:</strong>
                                                        <div>{{ $log->user_id ?? 'N/A' }}</div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <strong>Name:</strong>
                                                        <div>{{ $log->user_name ?? 'N/A' }}</div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <strong>Email:</strong>
                                                        <div>{{ $log->user_email ?? 'N/A' }}</div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <strong>IP Address:</strong>
                                                        <div>
                                                            <span class="badge bg-primary">
                                                                {{ $log->ip ?? 'N/A' }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <strong>Request URL:</strong>
                                                        <div class="text-break">
                                                            {{ $log->url ?? 'N/A' }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button"
                                                            class="btn btn-secondary waves-effect"
                                                            data-bs-dismiss="modal">
                                                        Close
                                                    </button>

                                                    @if($log->user_id)
                                                        @can('user-edit')
                                                            <a href="{{ route('users.edit', $log->user_id) }}"
                                                               class="btn btn-primary waves-effect waves-light">
                                                                Edit User
                                                            </a>
                                                        @endcan
                                                    @endif
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach

                            @foreach($logs as $log)
                                <div id="errorLogModal{{ $loop->index }}"
                                     class="modal fade"
                                     tabindex="-1"
                                     aria-labelledby="errorLogModalLabel{{ $loop->index }}"
                                     aria-hidden="true">
                                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                        <div class="modal-content">

                                            <div class="modal-header">
                                                <h5 class="modal-title" id="errorLogModalLabel{{ $loop->index }}">
                                                    Error Log Details
                                                </h5>

                                                <button type="button"
                                                        class="btn-close"
                                                        data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                            </div>

                                            <div class="modal-body">

                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Log File:</strong>
                                                        <div>
                                                            <span class="badge bg-primary">
                                                                {{ $log->file_name ?? 'N/A' }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <strong>Date Time:</strong>
                                                        <div>
                                                            {{ $log->date_time ? \Carbon\Carbon::parse($log->date_time)->timezone('Asia/Dhaka')->format('d M Y h:i A') : 'N/A' }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Exception:</strong>
                                                        <div>
                                                            <span class="badge bg-danger">
                                                                {{ $log->exception ?? 'N/A' }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-3">
                                                        <strong>Method:</strong>
                                                        <div>
                                                            <span class="badge bg-info">
                                                                {{ $log->method ?? 'N/A' }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-3">
                                                        <strong>Line:</strong>
                                                        <div>
                                                            <span class="badge bg-warning text-dark">
                                                                {{ $log->line ?? 'N/A' }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <strong>Message:</strong>
                                                    <div class="alert alert-danger py-2 px-3 mt-1 mb-0" role="alert">
                                                        <b>Line: </b><span class="badge bg-warning text-dark">{{ $log->line ?? 'N/A' }}</span>
                                                        <br> {{ $log->message ?? 'N/A' }}
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <strong>Error File:</strong>
                                                    <div class="text-break">
                                                        <code>{{ $log->error_file ?? 'N/A' }}</code>
                                                    </div>
                                                </div>

                                                <hr>

                                                <h6 class="mb-3">Request Information</h6>

                                                <div class="row mb-3">
                                                    <div class="col-md-8">
                                                        <strong>URL:</strong>
                                                        <div class="text-break">
                                                            {{ $log->url ?? 'N/A' }}
                                                        </div>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <strong>IP Address:</strong>
                                                        <div>
                                                            {{ $log->ip ?? 'N/A' }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <hr>

                                                <h6 class="mb-3">User Information</h6>

                                                <div class="row mb-3">
                                                    <div class="col-md-4">
                                                        <strong>User ID:</strong>
                                                        <div>{{ $log->user_id ?? 'N/A' }}</div>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <strong>Name:</strong>
                                                        <div>{{ $log->user_name ?? 'N/A' }}</div>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <strong>Email:</strong>
                                                        <div>{{ $log->user_email ?? 'N/A' }}</div>
                                                    </div>
                                                </div>

                                                <hr>

                                                <h6 class="mb-3">Request Input</h6>

                                                @if(!empty($log->input))
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered mb-0">
                                                            <thead class="table-light">
                                                            <tr>
                                                                <th width="30%">Field</th>
                                                                <th>Value</th>
                                                            </tr>
                                                            </thead>

                                                            <tbody>
                                                            @foreach($log->input as $key => $value)
                                                                <tr>
                                                                    <td class="fw-semibold">
                                                                        {{ ucwords(str_replace('_', ' ', $key)) }}
                                                                    </td>

                                                                    <td class="text-break">
                                                                        @if(is_array($value))
                                                                            <code>{{ json_encode($value) }}</code>
                                                                        @elseif(is_null($value))
                                                                            <span class="text-muted">NULL</span>
                                                                        @else
                                                                            {{ $value }}
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <p class="text-muted mb-0">
                                                        No request input found.
                                                    </p>
                                                @endif

                                                <hr>

                                                <h6 class="mb-3">Stack Trace</h6>

                                                @if(!empty($log->trace))
                                                    <pre class="bg-light p-3 rounded text-danger mb-0"
                                                         style="white-space: pre-wrap; max-height: 500px; overflow: auto;">{{ $log->trace }}</pre>
                                                @else
                                                    <p class="text-muted mb-0">
                                                        No stack trace found.
                                                    </p>
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
