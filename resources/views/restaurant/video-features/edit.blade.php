@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Blogger Video Features', 'url' => route('restaurant-video-features.index')],
                    ['label' => 'Edit'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Edit Video Feature</h5>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-sm-end mt-3 mt-sm-0">
                                        <a href="{{ route('restaurant-video-features.index') }}" class="btn btn-secondary waves-effect waves-light">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <form action="{{ route('restaurant-video-features.update', $videoFeature->id) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Title <span class="text-danger">*</span></label>
                                            <input type="text" name="title" value="{{ old('title', $videoFeature->title) }}"
                                                   class="form-control @error('title') is-invalid @enderror">
                                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Video Link <span class="text-danger">*</span></label>
                                            <input type="url" name="video_url" value="{{ old('video_url', $videoFeature->video_url) }}"
                                                   class="form-control @error('video_url') is-invalid @enderror"
                                                   placeholder="YouTube, Facebook, or Instagram video URL">
                                            <div class="form-text">
                                                YouTube (watch/youtu.be/shorts), Facebook (facebook.com or fb.watch), or Instagram (instagram.com/p, /reel, or /tv) links are supported.
                                            </div>
                                            @error('video_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Thumbnail Image</label>
                                            <input type="file" name="thumbnail" accept="image/*" class="form-control @error('thumbnail') is-invalid @enderror">
                                            <div class="form-text">Leave empty to keep the current thumbnail.</div>
                                            @error('thumbnail')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="display_order" value="{{ old('display_order', $videoFeature->display_order) }}" min="0"
                                                   class="form-control @error('display_order') is-invalid @enderror">
                                            @error('display_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label>Status</label>
                                        <div class="form-check form-switch mt-2">
                                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" @checked(old('is_active', $videoFeature->is_active) == 1)>
                                            <label class="form-check-label" for="isActive">Active</label>
                                        </div>
                                    </div>

                                    @if($videoFeature->thumbnail_path)
                                        <div class="col-12">
                                            <label class="form-label">Current Thumbnail</label>
                                            <div>
                                                <img src="{{ \Storage::url($videoFeature->thumbnail_path) }}"
                                                     alt="{{ $videoFeature->title }}" style="max-width: 320px;" class="rounded border">
                                            </div>
                                        </div>
                                    @elseif($videoFeature->youtube_video_id)
                                        <div class="col-12">
                                            <label class="form-label">Preview (auto-derived from YouTube)</label>
                                            <div>
                                                <img src="https://img.youtube.com/vi/{{ $videoFeature->youtube_video_id }}/hqdefault.jpg"
                                                     alt="{{ $videoFeature->title }}" style="max-width: 320px;" class="rounded border">
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('restaurant-video-features.index') }}" class="btn btn-light waves-effect">Cancel</a>
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
