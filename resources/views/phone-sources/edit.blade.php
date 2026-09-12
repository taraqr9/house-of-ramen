@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [['label' => 'Data Sources', 'url' => route('phone-sources.index')], ['label' => 'Edit']],
            ])

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-6"><h5 class="card-title mb-1">{{ $source->name }}</h5></div>
                                <div class="col-sm-6">
                                    <div class="text-sm-end mt-3 mt-sm-0">
                                        <a href="{{ route('phone-sources.index') }}" class="btn btn-secondary waves-effect waves-light"><i class="mdi mdi-arrow-left me-1"></i> Back</a>
                                    </div>
                                </div>
                            </div>

                            <form action="{{ route('phone-sources.update', $source->id) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="mb-3">
                                    <label class="form-label">Reliability Score (0-100) <span class="text-danger">*</span></label>
                                    <input type="number" min="0" max="100" name="reliability_score"
                                           value="{{ old('reliability_score', $source->reliability_score) }}"
                                           class="form-control @error('reliability_score') is-invalid @enderror">
                                    <small class="text-muted">Drives confidence scoring and conflict auto-resolution for records from this source.</small>
                                    @error('reliability_score')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="requires_review" value="1" class="form-check-input" id="requiresReview" @checked(old('requires_review', $source->requires_review) == 1)>
                                        <label class="form-check-label" for="requiresReview">Always route records from this source to review, regardless of confidence</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" @checked(old('is_active', $source->is_active) == 1)>
                                        <label class="form-check-label" for="isActive">Active (source is used by import runs)</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror"
                                              placeholder="e.g. mark unreliable and why">{{ old('notes', $source->notes) }}</textarea>
                                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('phone-sources.index') }}" class="btn btn-light waves-effect">Cancel</a>
                                        <button type="submit" class="btn btn-primary waves-effect waves-light"><i class="mdi mdi-content-save-outline me-1"></i> Update</button>
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
