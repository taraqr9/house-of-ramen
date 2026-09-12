@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Restaurant'],
                    ['label' => 'Settings'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Restaurant Settings</h5>

                            <form action="{{ route('restaurant.update') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Name <span class="text-danger">*</span></label>
                                            <input type="text" name="name" value="{{ old('name', $restaurant->name) }}"
                                                   class="form-control @error('name') is-invalid @enderror">
                                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tagline</label>
                                            <input type="text" name="tagline" value="{{ old('tagline', $restaurant->tagline) }}"
                                                   class="form-control @error('tagline') is-invalid @enderror"
                                                   placeholder="e.g. Modern ramen &amp; Japanese-Korean comfort food">
                                            @error('tagline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $restaurant->description) }}</textarea>
                                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <div class="form-text">Shown on the public About page. Keep it accurate - no invented history or claims.</div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Logo</label>
                                            <input type="file" name="logo" accept="image/*" class="form-control @error('logo') is-invalid @enderror">
                                            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            @if($restaurant->logo_path)
                                                <img src="{{ \Storage::url($restaurant->logo_path) }}" alt="Current logo" class="img-thumbnail mt-2" style="max-height: 80px;">
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Cover / Hero Image</label>
                                            <input type="file" name="cover_image" accept="image/*" class="form-control @error('cover_image') is-invalid @enderror">
                                            @error('cover_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            @if($restaurant->cover_image_path)
                                                <img src="{{ \Storage::url($restaurant->cover_image_path) }}" alt="Current cover image" class="img-thumbnail mt-2" style="max-height: 80px;">
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Phone</label>
                                            <input type="text" name="phone" value="{{ old('phone', $restaurant->phone) }}"
                                                   class="form-control @error('phone') is-invalid @enderror">
                                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" value="{{ old('email', $restaurant->email) }}"
                                                   class="form-control @error('email') is-invalid @enderror">
                                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">Address</label>
                                            <input type="text" name="address" value="{{ old('address', $restaurant->address) }}"
                                                   class="form-control @error('address') is-invalid @enderror">
                                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Area</label>
                                            <input type="text" name="area" value="{{ old('area', $restaurant->area) }}"
                                                   class="form-control @error('area') is-invalid @enderror"
                                                   placeholder="e.g. Uttara, Dhaka">
                                            @error('area')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Facebook URL</label>
                                            <input type="url" name="facebook_url" value="{{ old('facebook_url', $restaurant->facebook_url) }}"
                                                   class="form-control @error('facebook_url') is-invalid @enderror">
                                            @error('facebook_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <div class="form-text">Leave blank until a real account exists - the public site only shows this icon when set.</div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Instagram URL</label>
                                            <input type="url" name="instagram_url" value="{{ old('instagram_url', $restaurant->instagram_url) }}"
                                                   class="form-control @error('instagram_url') is-invalid @enderror">
                                            @error('instagram_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Delivery Platforms</label>
                                            <input type="text" name="delivery_platforms"
                                                   value="{{ old('delivery_platforms', implode(', ', $restaurant->delivery_platforms ?? [])) }}"
                                                   class="form-control @error('delivery_platforms') is-invalid @enderror"
                                                   placeholder="e.g. Foodi, foodpanda">
                                            @error('delivery_platforms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <div class="form-text">Comma-separated platform names, shown as badges on the public site.</div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mt-1">
                                        <label>Status</label>
                                        <div class="form-check form-switch mt-2">
                                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" @checked(old('is_active', $restaurant->is_active) == 1)>
                                            <label class="form-check-label" for="isActive">Active</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
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
