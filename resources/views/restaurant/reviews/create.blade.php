@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Reviews', 'url' => route('restaurant-reviews.index')],
                    ['label' => 'Add'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Add Review</h5>
                                    <p class="text-muted mb-0 small">Copy this in from the restaurant's actual Google listing - never invented.</p>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-sm-end mt-3 mt-sm-0">
                                        <a href="{{ route('restaurant-reviews.index') }}" class="btn btn-secondary waves-effect waves-light">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <form action="{{ route('restaurant-reviews.store') }}" method="POST">
                                @csrf

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Reviewer Name <span class="text-danger">*</span></label>
                                            <input type="text" name="author_name" value="{{ old('author_name') }}"
                                                   class="form-control @error('author_name') is-invalid @enderror"
                                                   placeholder="e.g. Moin Akon" required>
                                            @error('author_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Rating <span class="text-danger">*</span></label>
                                            <select name="rating" class="form-control select2 @error('rating') is-invalid @enderror" required>
                                                @for ($i = 5; $i >= 1; $i--)
                                                    <option value="{{ $i }}" @selected((int) old('rating', 5) === $i)>{{ $i }} Star{{ $i > 1 ? 's' : '' }}</option>
                                                @endfor
                                            </select>
                                            @error('rating')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Reviewed On</label>
                                            <input type="date" name="reviewed_at" value="{{ old('reviewed_at') }}"
                                                   class="form-control @error('reviewed_at') is-invalid @enderror">
                                            @error('reviewed_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Review Text <span class="text-danger">*</span></label>
                                            <textarea name="review_text" rows="5"
                                                      class="form-control @error('review_text') is-invalid @enderror"
                                                      placeholder="Paste the review text as written by the customer" required>{{ old('review_text') }}</textarea>
                                            @error('review_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="display_order" value="{{ old('display_order', 0) }}" min="0"
                                                   class="form-control @error('display_order') is-invalid @enderror">
                                            @error('display_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label>Status</label>
                                        <div class="form-check form-switch mt-2">
                                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" @checked(old('is_active', '1') === '1')>
                                            <label class="form-check-label" for="isActive">Active</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('restaurant-reviews.index') }}" class="btn btn-light waves-effect">Cancel</a>
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
            $('.select2').select2({width: '100%', minimumResultsForSearch: -1});
        });
    </script>
@endsection
