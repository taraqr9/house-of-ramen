@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [['label' => 'Phone Data'], ['label' => 'Phones']],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-6"><h5 class="card-title mb-1">Phone Filters</h5></div>
                                @can('phone-create')
                                    <div class="col-sm-6">
                                        <div class="d-flex justify-content-end">
                                            <a href="{{ route('phones.create') }}" class="btn btn-success"><i class="mdi mdi-plus"></i> Add Phone</a>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <form action="{{ route('phones.index') }}" method="GET">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Keyword</label>
                                        <input type="text" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="Search by model">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Brand</label>
                                        <select name="brand_id" class="form-control select2">
                                            <option value="">All Brands</option>
                                            @foreach($brands as $brand)
                                                <option value="{{ $brand->id }}" @selected(request('brand_id') == $brand->id)>{{ $brand->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-control select2">
                                            <option value="">All Status</option>
                                            @foreach(\App\Enums\PhoneStatusEnum::options() as $value => $label)
                                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Category</label>
                                        <select name="category" class="form-control select2">
                                            <option value="">All Categories</option>
                                            @foreach(['flagship' => 'Flagship', 'midrange' => 'Midrange', 'budget' => 'Budget', 'entry' => 'Entry'] as $value => $label)
                                                <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-check form-switch mt-4">
                                            <input type="checkbox" name="low_confidence" value="1" class="form-check-input" id="lowConfidence" @checked(request('low_confidence') === '1')>
                                            <label class="form-check-label" for="lowConfidence">Low confidence only</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-check form-switch mt-4">
                                            <input type="checkbox" name="no_image" value="1" class="form-check-input" id="noImage" @checked(request('no_image') === '1')>
                                            <label class="form-check-label" for="noImage">No image phones</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-check form-switch mt-4">
                                            <input type="checkbox" name="no_price" value="1" class="form-check-input" id="noPrice" @checked(request('no_price') === '1')>
                                            <label class="form-check-label" for="noPrice">Price unavailable</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('phones.index') }}" class="btn btn-light waves-effect">Reset</a>
                                    <button type="submit" class="btn btn-primary waves-effect waves-light"><i class="mdi mdi-magnify me-1"></i> Search</button>
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
                                        <th>Phone</th>
                                        <th>Brand</th>
                                        <th>Category</th>
                                        <th>BD Price (from)</th>
                                        <th>Confidence</th>
                                        <th>Status</th>
                                        <th>Updated</th>
                                        <th class="text-center" style="width: 160px;">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($phones as $phone)
                                        @php
                                            $lowestPrice = $phone->variants->flatMap->prices->sortBy('amount')->first();
                                        @endphp
                                        <tr>
                                            <td>
                                                <strong>{{ $phone->name }}</strong>
                                                @if($phone->reviews_count > 0)
                                                    <span class="badge bg-warning ms-1" title="Pending review">{{ $phone->reviews_count }} review</span>
                                                @endif
                                            </td>
                                            <td>{{ $phone->brand->name }}</td>
                                            <td>{{ $phone->category ? ucfirst($phone->category) : '-' }}</td>
                                            <td>{{ $lowestPrice ? '৳'.number_format($lowestPrice->amount) : '-' }}</td>
                                            <td>
                                                @if($phone->overall_confidence !== null)
                                                    <span class="badge {{ $phone->overall_confidence >= config('phone_confidence.auto_approve_threshold') ? 'bg-success' : 'bg-warning' }}">
                                                        {{ $phone->overall_confidence }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td><span class="badge {{ $phone->status->badgeClass() }}">{{ $phone->status->label() }}</span></td>
                                            <td>{{ $phone->updated_at->diffForHumans() }}</td>
                                            <td class="text-center">
                                                @can('phone-edit')
                                                    <a href="{{ route('phones.edit', $phone->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                                @endcan
                                                @can('phone-delete')
                                                    <form action="{{ route('phones.destroy', $phone->id) }}" method="POST" class="d-inline delete-form">
                                                        @csrf @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-danger delete-btn">Delete</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="text-center text-muted">No phones found.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if($phones instanceof \Illuminate\Pagination\AbstractPaginator)
                                <div class="d-flex justify-content-end mt-3">{{ $phones->links('partials.pagination') }}</div>
                            @endif
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
                title: 'Are you sure?', text: 'This phone and all its data will be deleted', icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#f46a6a', cancelButtonColor: '#74788d', confirmButtonText: 'Yes delete it'
            }).then((result) => { if (result.isConfirmed) form.submit(); });
        });
    </script>
@endsection
