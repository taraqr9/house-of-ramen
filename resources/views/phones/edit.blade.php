@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [['label' => 'Phones', 'url' => route('phones.index')], ['label' => $phone->name]],
            ])

            @if($phone->reviews->isNotEmpty() || $phone->conflicts->isNotEmpty())
                <div class="alert alert-warning d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Needs attention:</strong>
                        {{ $phone->reviews->count() }} pending review(s), {{ $phone->conflicts->count() }} open conflict(s).
                    </div>
                    <a href="{{ route('data-review.index') }}" class="btn btn-sm btn-dark">Go to Review Queue</a>
                </div>
            @endif

            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="card text-center"><div class="card-body">
                        <h6 class="text-muted mb-1">Overall Confidence</h6>
                        <h3 class="mb-0">{{ $phone->overall_confidence ?? '-' }}</h3>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center"><div class="card-body">
                        <h6 class="text-muted mb-1">Identity</h6>
                        <h5 class="mb-0">{{ $phone->identity_confidence ?? '-' }}</h5>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center"><div class="card-body">
                        <h6 class="text-muted mb-1">Specification</h6>
                        <h5 class="mb-0">{{ $phone->spec_confidence ?? '-' }}</h5>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center"><div class="card-body">
                        <h6 class="text-muted mb-1">Software</h6>
                        <h5 class="mb-0">{{ $phone->software_confidence ?? '-' }}</h5>
                    </div></div>
                </div>
            </div>

            <form action="{{ route('phones.update', $phone->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-sm-6"><h5 class="card-title mb-1">Edit Phone</h5></div>
                                    <div class="col-sm-6">
                                        <div class="text-sm-end mt-3 mt-sm-0">
                                            <a href="{{ route('phones.index') }}" class="btn btn-secondary waves-effect waves-light"><i class="mdi mdi-arrow-left me-1"></i> Back</a>
                                        </div>
                                    </div>
                                </div>

                                @include('phones._form', ['phone' => $phone, 'spec' => $phone->spec])

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('phones.index') }}" class="btn btn-light waves-effect">Cancel</a>
                                        <button type="submit" class="btn btn-primary waves-effect waves-light"><i class="mdi mdi-content-save-outline me-1"></i> Update</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-12"><h5 class="card-title mb-1">Images</h5>
                                    <p class="text-muted mb-0">The verified image marked "Primary" is what the public site shows. Needs-review images are never shown publicly until verified.</p>
                                </div>
                            </div>

                            @can('phone-edit')
                                <form action="{{ route('phones.images.store', $phone->id) }}" method="POST" enctype="multipart/form-data" class="row g-2 align-items-end mb-4">
                                    @csrf
                                    <div class="col-sm-6 col-md-4">
                                        <label class="form-label">Upload image</label>
                                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control @error('image') is-invalid @enderror" required>
                                        @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <div class="form-text">JPEG, PNG, or WebP, up to 8MB. Uploading replaces the current primary image immediately.</div>
                                    </div>
                                    @if($phone->variants->isNotEmpty())
                                        <div class="col-sm-4 col-md-3">
                                            <label class="form-label">Variant (optional)</label>
                                            <select name="variant_id" class="form-control select2">
                                                <option value="">Whole phone (no specific variant)</option>
                                                @foreach($phone->variants as $variant)
                                                    <option value="{{ $variant->id }}">{{ $variant->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                    <div class="col-sm-2 col-md-2">
                                        <button type="submit" class="btn btn-primary"><i class="mdi mdi-upload me-1"></i> Upload</button>
                                    </div>
                                </form>
                            @endcan

                            <div class="row g-3">
                                @forelse($phone->images as $image)
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <div class="card h-100 {{ $image->is_primary && $image->status === \App\Enums\ImageStatusEnum::VERIFIED ? 'border-success' : '' }}">
                                            <div class="d-flex align-items-center justify-content-center bg-light" style="height: 140px; overflow: hidden;">
                                                @if($image->url)
                                                    <img src="{{ $image->url }}" alt="{{ $phone->name }}" style="max-width: 100%; max-height: 140px; object-fit: contain;">
                                                @else
                                                    <span class="text-muted small">No file</span>
                                                @endif
                                            </div>
                                            <div class="card-body p-2">
                                                <div class="mb-1">
                                                    <span class="badge {{ $image->status->badgeClass() }}">{{ $image->status->label() }}</span>
                                                    @if($image->is_primary && $image->status === \App\Enums\ImageStatusEnum::VERIFIED)<span class="badge bg-primary">Primary</span>@endif
                                                </div>
                                                <div class="small text-muted mb-1">Confidence: {{ $image->match_confidence ?? '-' }}</div>
                                                <div class="small text-muted mb-2 text-truncate" title="{{ $image->attribution }}">{{ $image->license ?? 'No license' }}</div>
                                                @can('phone-edit')
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @if($image->status !== \App\Enums\ImageStatusEnum::VERIFIED)
                                                            <form action="{{ route('phones.images.verify', [$phone->id, $image->id]) }}" method="POST" class="d-inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-xs btn-success" style="font-size: .7rem;">Verify</button>
                                                            </form>
                                                        @endif
                                                        @if(!$image->is_primary && $image->status === \App\Enums\ImageStatusEnum::VERIFIED)
                                                            <form action="{{ route('phones.images.set-primary', [$phone->id, $image->id]) }}" method="POST" class="d-inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-xs btn-primary" style="font-size: .7rem;">Make Primary</button>
                                                            </form>
                                                        @endif
                                                        @if($image->status !== \App\Enums\ImageStatusEnum::REJECTED)
                                                            <form action="{{ route('phones.images.reject', [$phone->id, $image->id]) }}" method="POST" class="d-inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-xs btn-warning" style="font-size: .7rem;">Reject</button>
                                                            </form>
                                                        @endif
                                                        <form action="{{ route('phones.images.destroy', [$phone->id, $image->id]) }}" method="POST" class="d-inline delete-form">
                                                            @csrf @method('DELETE')
                                                            <button type="button" class="btn btn-xs btn-danger delete-btn" style="font-size: .7rem;">Delete</button>
                                                        </form>
                                                    </div>
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12 text-muted">No images yet. Upload one above, run <code>php artisan phones:collect-images {{ $phone->id }}</code>, or wait for the next automated collection pass.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-6"><h5 class="card-title mb-1">Variants & Bangladesh Pricing</h5></div>
                                @can('phone_variant-create')
                                    <div class="col-sm-6">
                                        <div class="d-flex justify-content-end">
                                            <a href="{{ route('phones.variants.create', $phone->id) }}" class="btn btn-success btn-sm">
                                                <i class="mdi mdi-plus"></i> Add Variant
                                            </a>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Variant</th>
                                        <th>Official BD</th>
                                        <th>Unofficial BD</th>
                                        <th>Availability</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 140px;">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($phone->variants as $variant)
                                        <tr>
                                            <td>
                                                {{ $variant->label() }}
                                                <span class="badge bg-info ms-1">{{ \App\Enums\PhoneRegionEnum::resolve($variant->region)->label() }}</span>
                                                @if($variant->is_official_bd)<span class="badge bg-success ms-1">Official BD</span>@endif
                                            </td>
                                            <td>
                                                @php $off = $variant->prices->firstWhere('price_type', \App\Enums\PriceTypeEnum::OFFICIAL_BD); @endphp
                                                {{ $off ? '৳'.number_format($off->amount) : '-' }}
                                            </td>
                                            <td>
                                                @php $unoff = $variant->prices->firstWhere('price_type', \App\Enums\PriceTypeEnum::UNOFFICIAL_BD); @endphp
                                                {{ $unoff ? '৳'.number_format($unoff->amount) : '-' }}
                                            </td>
                                            <td>
                                                @php $avail = $variant->availabilities->first(); @endphp
                                                @if($avail)
                                                    <span class="badge {{ $avail->status->badgeClass() }}">{{ $avail->status->label() }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $variant->is_active ? 'bg-success' : 'bg-danger' }}">
                                                    {{ $variant->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @can('phone_variant-edit')
                                                    <a href="{{ route('phones.variants.edit', [$phone->id, $variant->id]) }}" class="btn btn-sm btn-warning">Edit</a>
                                                @endcan
                                                @can('phone_variant-delete')
                                                    <form action="{{ route('phones.variants.destroy', [$phone->id, $variant->id]) }}" method="POST" class="d-inline delete-form">
                                                        @csrf @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-danger delete-btn">Delete</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted">No variants yet. Add one to record RAM/storage and Bangladesh pricing.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
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
        $(document).ready(function () { $('.select2').select2({width: '100%', allowClear: true}); });
        $(document).on('click', '.delete-btn', function () {
            let form = $(this).closest('form');
            Swal.fire({
                title: 'Are you sure?', text: 'This variant (and its prices) will be deleted', icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#f46a6a', cancelButtonColor: '#74788d', confirmButtonText: 'Yes delete it'
            }).then((result) => { if (result.isConfirmed) form.submit(); });
        });
    </script>
@endsection
