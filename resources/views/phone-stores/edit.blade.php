@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [['label' => 'Stores', 'url' => route('phone-stores.index')], ['label' => 'Edit']],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-6"><h5 class="card-title mb-1">Edit Store</h5></div>
                                <div class="col-sm-6">
                                    <div class="text-sm-end mt-3 mt-sm-0">
                                        <a href="{{ route('phone-stores.index') }}" class="btn btn-secondary waves-effect waves-light"><i class="mdi mdi-arrow-left me-1"></i> Back</a>
                                    </div>
                                </div>
                            </div>

                            <form action="{{ route('phone-stores.update', $store->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Name <span class="text-danger">*</span></label>
                                            <input type="text" name="name" value="{{ old('name', $store->name) }}" class="form-control @error('name') is-invalid @enderror">
                                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Type <span class="text-danger">*</span></label>
                                            <select name="type" class="form-control select2 @error('type') is-invalid @enderror">
                                                @foreach(['official' => 'Official', 'authorized' => 'Authorized', 'marketplace' => 'Marketplace', 'other' => 'Other'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('type', $store->type) === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Website URL</label>
                                            <input type="text" name="website_url" value="{{ old('website_url', $store->website_url) }}" class="form-control @error('website_url') is-invalid @enderror">
                                            @error('website_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6 mt-3">
                                        <label>Status</label>
                                        <div class="form-check form-switch mt-2">
                                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" @checked(old('is_active', $store->is_active) == 1)>
                                            <label class="form-check-label" for="isActive">Active</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('phone-stores.index') }}" class="btn btn-light waves-effect">Cancel</a>
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

@section('JScript')
    <script>$(document).ready(function () { $('.select2').select2({width: '100%', allowClear: true}); });</script>
@endsection
