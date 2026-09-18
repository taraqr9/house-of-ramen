@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Menu Items', 'url' => route('restaurant-menu-items.index')],
                    ['label' => 'Add'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Add Menu Item</h5>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-sm-end mt-3 mt-sm-0">
                                        <a href="{{ route('restaurant-menu-items.index') }}" class="btn btn-secondary waves-effect waves-light">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <form action="{{ route('restaurant-menu-items.store') }}" method="POST" enctype="multipart/form-data">
                                @csrf

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Name <span class="text-danger">*</span></label>
                                            <input type="text" name="name" value="{{ old('name') }}"
                                                   class="form-control @error('name') is-invalid @enderror"
                                                   placeholder="e.g. Tonkatsu Ramen">
                                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Category <span class="text-danger">*</span></label>
                                            <select name="restaurant_menu_category_id" class="form-control select2 @error('restaurant_menu_category_id') is-invalid @enderror">
                                                <option value="">Select a category</option>
                                                @foreach($categories as $category)
                                                    <option value="{{ $category->id }}" @selected(old('restaurant_menu_category_id') == $category->id)>{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('restaurant_menu_category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Price (৳) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" min="0" name="price" value="{{ old('price') }}"
                                                   class="form-control @error('price') is-invalid @enderror">
                                            @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-5">
                                        <div class="mb-3">
                                            <label class="form-label">Price Note</label>
                                            <input type="text" name="price_note" value="{{ old('price_note') }}"
                                                   class="form-control @error('price_note') is-invalid @enderror"
                                                   placeholder="e.g. 8pcs: ৳690">
                                            @error('price_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="display_order" value="{{ old('display_order', 0) }}" min="0"
                                                   class="form-control @error('display_order') is-invalid @enderror">
                                            @error('display_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Primary Image</label>
                                            <input type="file" name="image" accept="image/*" class="form-control @error('image') is-invalid @enderror">
                                            @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Additional Gallery Images</label>
                                            <input type="file" name="gallery_images[]" accept="image/*" multiple class="form-control @error('gallery_images') is-invalid @enderror">
                                            @error('gallery_images')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label>Featured</label>
                                        <div class="form-check form-switch mt-2">
                                            <input type="checkbox" name="is_featured" value="1" class="form-check-input" id="isFeatured" @checked(old('is_featured'))>
                                            <label class="form-check-label" for="isFeatured">Show on homepage</label>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label>New Item</label>
                                        <div class="form-check form-switch mt-2">
                                            <input type="checkbox" name="is_new" value="1" class="form-check-input" id="isNew" @checked(old('is_new'))>
                                            <label class="form-check-label" for="isNew">Show in "New on the Menu"</label>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label>Availability</label>
                                        <div class="form-check form-switch mt-2">
                                            <input type="checkbox" name="is_available" value="1" class="form-check-input" id="isAvailable" @checked(old('is_available', '1') === '1')>
                                            <label class="form-check-label" for="isAvailable">Available</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('restaurant-menu-items.index') }}" class="btn btn-light waves-effect">Cancel</a>
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

@section('JScript')
    <script>
        $(document).ready(function () {
            $('.select2').select2({width: '100%', allowClear: true});
        });
    </script>
@endsection
