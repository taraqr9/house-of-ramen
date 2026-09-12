@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Restaurant'],
                    ['label' => 'Gallery'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Restaurant Gallery</h5>
                                </div>

                                @can('restaurant_gallery_image-create')
                                    <div class="col-sm-6">
                                        <div class="d-flex justify-content-end">
                                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addImageModal">
                                                <i class="mdi mdi-plus"></i> Add Image
                                            </button>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <div class="row g-3">
                                @forelse($images as $image)
                                    <div class="col-6 col-md-3 col-xl-2">
                                        <div class="card h-100">
                                            <img src="{{ \Storage::url($image->path) }}" class="card-img-top" style="height: 140px; object-fit: cover;" alt="{{ $image->caption ?? $image->category->label() }}">
                                            <div class="card-body p-2">
                                                <span class="badge bg-secondary mb-1">{{ $image->category->label() }}</span>
                                                <p class="small text-muted mb-2 text-truncate" title="{{ $image->caption }}">{{ $image->caption ?: '—' }}</p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge {{ $image->is_active ? 'bg-success' : 'bg-danger' }}">{{ $image->is_active ? 'Active' : 'Hidden' }}</span>
                                                    @can('restaurant_gallery_image-delete')
                                                        <form action="{{ route('restaurant-gallery-images.destroy', $image->id) }}" method="POST" class="d-inline delete-form">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="button" class="btn btn-sm btn-outline-danger delete-btn"><i class="mdi mdi-delete"></i></button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12 text-center text-muted py-4">No gallery images yet.</div>
                                @endforelse
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                {{ $images->links('partials.pagination') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="addImageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('restaurant-gallery-images.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Gallery Image</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Image <span class="text-danger">*</span></label>
                            <input type="file" name="image" accept="image/*" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-control" required>
                                @foreach(\App\Enums\GalleryCategoryEnum::options() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Caption</label>
                            <input type="text" name="caption" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Display Order</label>
                            <input type="number" name="display_order" value="0" min="0" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('JScript')
    <script>
        $(document).on('click', '.delete-btn', function () {
            let form = $(this).closest('form');

            Swal.fire({
                title: 'Are you sure?',
                text: 'This image will be removed from the gallery',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f46a6a',
                cancelButtonColor: '#74788d',
                confirmButtonText: 'Yes remove it'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    </script>
@endsection
