@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Menus', 'url' => route('menus.index')],
                    ['label' => 'Edit']
                ],
            ])

            <div class="card">
                <div class="card-body">

                    <form action="{{ route('menus.update', $menu->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">

                            <div class="col-md-6">
                                <label>Title <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="title"
                                       class="form-control @error('title') is-invalid @enderror"
                                       value="{{ old('title', $menu->title) }}"
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
                                       value="{{ old('icon', $menu->icon) }}">

                                @error('icon')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mt-3">
                                <label>Route</label>
                                <input type="text"
                                       name="route"
                                       class="form-control @error('route') is-invalid @enderror"
                                       value="{{ old('route', $menu->route) }}">

                                @error('route')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mt-3">
                                <label>URL</label>
                                <input type="text"
                                       name="url"
                                       class="form-control @error('url') is-invalid @enderror"
                                       value="{{ old('url', $menu->url) }}">

                                @error('url')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mt-3">
                                <label>Permission</label>
                                <input type="text"
                                       name="permission"
                                       class="form-control @error('permission') is-invalid @enderror"
                                       value="{{ old('permission', $menu->permission) }}">

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
                                        <option value="{{ $parent->id }}"
                                            {{ old('parent_id', $menu->parent_id) == $parent->id ? 'selected' : '' }}>
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
                                       value="{{ old('serial', $menu->serial) }}"
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
                                        {{ old('is_active', $menu->is_active->value) ? 'checked' : '' }}>

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
                                          placeholder="Enter one route pattern per line">{{ old('active_routes',implode("\n", $menu->active_routes ?? [])) }}
                                    </textarea>

                                <small class="text-muted">
                                    Enter one route name or wildcard pattern per line.
                                    Example: salary-grades.* and salary-breakdowns.*
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
                            <a href="{{ route('menus.index') }}" class="btn btn-secondary">
                                Cancel
                            </a>

                            <button type="submit" class="btn btn-primary">
                                Update
                            </button>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
@endsection
