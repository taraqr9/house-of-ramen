@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Menus', 'url' => route('menus.index')],
                    ['label' => 'Create']
                ],
            ])

            <div class="card">
                <div class="card-body">

                    <form action="{{ route('menus.store') }}" method="POST">
                        @csrf

                        <div class="row">

                            <div class="col-md-6">
                                <label>Title <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="title"
                                       class="form-control @error('title') is-invalid @enderror"
                                       value="{{ old('title') }}"
                                       required>

                                @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label>Icon</label>
                                <input type="text"
                                       name="icon"
                                       class="form-control @error('icon') is-invalid @enderror"
                                       value="{{ old('icon') }}">

                                @error('icon')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mt-3">
                                <label>
                                    Route
                                    <small class="text-muted ms-1">(example: menus.index)</small>
                                </label>

                                <input type="text"
                                       name="route"
                                       class="form-control @error('route') is-invalid @enderror"
                                       value="{{ old('route') }}">

                                @error('route')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mt-3">
                                <label>
                                    URL
                                    <small class="text-muted ms-1">(example: /reports/monthly)</small>
                                </label>

                                <input type="text"
                                       name="url"
                                       class="form-control @error('url') is-invalid @enderror"
                                       value="{{ old('url') }}">

                                @error('url')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mt-3">
                                <label>Permission</label>
                                <input type="text"
                                       name="permission"
                                       class="form-control @error('permission') is-invalid @enderror"
                                       value="{{ old('permission') }}">

                                @error('permission')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mt-3">
                                <label>Parent Menu</label>
                                <select name="parent_id"
                                        class="form-control select2 @error('parent_id') is-invalid @enderror">
                                    <option value="">None</option>
                                    @foreach($parents as $parent)
                                        <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                            {{ $parent->title }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('parent_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mt-3">
                                <label>Serial <span class="text-danger">*</span></label>
                                <input type="number"
                                       name="serial"
                                       class="form-control @error('serial') is-invalid @enderror"
                                       value="{{ old('serial', 0) }}"
                                       min="0"
                                       required>

                                @error('serial')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mt-3">
                                <label>Status</label>

                                <div class="form-check form-switch mt-2">
                                    <input type="checkbox"
                                           name="is_active"
                                           value="1"
                                           class="form-check-input"
                                           id="isActive"
                                        @checked(old('is_active', '1') === '1')>

                                    <label class="form-check-label" for="isActive">
                                        Active
                                    </label>
                                </div>

                                @error('is_active')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12 mt-3">
                                <label for="active_routes"
                                       class="form-label">
                                    Active Route Patterns
                                </label>

                                <textarea name="active_routes"
                                          id="active_routes"
                                          rows="4"
                                          class="form-control @error('active_routes') is-invalid @enderror"
                                          placeholder="Enter one route pattern per line&#10;Example:&#10;salary-grades.*&#10;salary-breakdowns.*">{{ old('active_routes') }}</textarea>

                                <small class="text-muted">
                                    Enter one route name or wildcard pattern per line.
                                    Leave empty to automatically use the menu resource route.
                                </small>

                                @error('active_routes')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror

                                @error('active_routes.*')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>

                        </div>

                        <div class="mt-4 text-end">
                            <button class="btn btn-primary">Save</button>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
@endsection
