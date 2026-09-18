@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Restaurant'],
                    ['label' => 'Blogger Video Features'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Blogger Video Features</h5>
                                </div>

                                @can('restaurant_video_feature-create')
                                    <div class="col-sm-6">
                                        <div class="d-flex justify-content-end">
                                            <a href="{{ route('restaurant-video-features.create') }}" class="btn btn-success">
                                                <i class="mdi mdi-plus"></i> Add Video
                                            </a>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <form action="{{ route('restaurant-video-features.index') }}" method="GET">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label">Keyword</label>
                                        <input type="text" name="keyword" value="{{ request('keyword') }}"
                                               class="form-control" placeholder="Search by title">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select name="is_active" class="form-control select2">
                                            <option value="">All Status</option>
                                            <option value="1" @selected(request('is_active') === '1')>Active</option>
                                            <option value="0" @selected(request('is_active') === '0')>Inactive</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('restaurant-video-features.index') }}" class="btn btn-light waves-effect">Reset</a>
                                    <button type="submit" class="btn btn-primary waves-effect waves-light">
                                        <i class="mdi mdi-magnify me-1"></i> Search
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
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;">Order</th>
                                        <th style="width: 100px;">Thumbnail</th>
                                        <th>Title</th>
                                        <th style="width: 100px;">Platform</th>
                                        <th>Video Link</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 160px;">Action</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($videoFeatures as $videoFeature)
                                        <tr>
                                            <td>{{ $videoFeature->display_order }}</td>
                                            <td>
                                                @if($videoFeature->thumbnail_path)
                                                    <img src="{{ \Storage::url($videoFeature->thumbnail_path) }}"
                                                         alt="{{ $videoFeature->title }}" style="width: 80px; height: 45px; object-fit: cover;" class="rounded">
                                                @elseif($videoFeature->youtube_video_id)
                                                    <img src="https://img.youtube.com/vi/{{ $videoFeature->youtube_video_id }}/default.jpg"
                                                         alt="{{ $videoFeature->title }}" style="width: 80px; height: 45px; object-fit: cover;" class="rounded">
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td><strong>{{ $videoFeature->title }}</strong></td>
                                            <td>
                                                <span class="badge bg-secondary text-capitalize">{{ $videoFeature->platform ?? 'unknown' }}</span>
                                            </td>
                                            <td>
                                                <a href="{{ $videoFeature->video_url }}" target="_blank" rel="noopener" class="text-truncate d-inline-block" style="max-width: 220px;">
                                                    {{ $videoFeature->video_url }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge {{ $videoFeature->is_active ? 'bg-success' : 'bg-danger' }}">
                                                    {{ $videoFeature->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @can('restaurant_video_feature-edit')
                                                    <a href="{{ route('restaurant-video-features.edit', $videoFeature->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                                @endcan

                                                @can('restaurant_video_feature-delete')
                                                    <form action="{{ route('restaurant-video-features.destroy', $videoFeature->id) }}" method="POST" class="d-inline delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-danger delete-btn">Delete</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No video features found.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                {{ $videoFeatures->links('partials.pagination') }}
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
            $('.select2').select2({width: '100%', allowClear: true});
        });

        $(document).on('click', '.delete-btn', function () {
            let form = $(this).closest('form');

            Swal.fire({
                title: 'Are you sure?',
                text: 'This video feature will be deleted',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f46a6a',
                cancelButtonColor: '#74788d',
                confirmButtonText: 'Yes delete it'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    </script>
@endsection
