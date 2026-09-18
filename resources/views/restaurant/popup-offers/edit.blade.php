@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Popup Offers', 'url' => route('restaurant-popup-offers.index')],
                    ['label' => 'Edit'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Edit Popup Offer</h5>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-sm-end mt-3 mt-sm-0">
                                        <a href="{{ route('restaurant-popup-offers.index') }}" class="btn btn-secondary waves-effect waves-light">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <form action="{{ route('restaurant-popup-offers.update', $popupOffer->id) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Title <span class="text-muted">(admin reference only, not shown to visitors)</span></label>
                                            <input type="text" name="title" value="{{ old('title', $popupOffer->title) }}"
                                                   class="form-control @error('title') is-invalid @enderror">
                                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="display_order" value="{{ old('display_order', $popupOffer->display_order) }}" min="0"
                                                   class="form-control @error('display_order') is-invalid @enderror">
                                            @error('display_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label>Status</label>
                                        <div class="form-check form-switch mt-2">
                                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" @checked(old('is_active', $popupOffer->is_active) == 1)>
                                            <label class="form-check-label" for="isActive">Active</label>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Image</label>
                                            <input type="file" name="image" accept="image/*" class="form-control @error('image') is-invalid @enderror">
                                            <div class="form-text">Leave empty to keep the current image.</div>
                                            @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <img src="{{ \Storage::url($popupOffer->image_path) }}" alt="{{ $popupOffer->title }}" class="img-thumbnail mt-2" style="max-height: 120px;">
                                        </div>
                                    </div>
                                </div>

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('restaurant-popup-offers.index') }}" class="btn btn-light waves-effect">Cancel</a>
                                        <button type="submit" class="btn btn-primary waves-effect waves-light">
                                            <i class="mdi mdi-content-save-outline me-1"></i> Save
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
