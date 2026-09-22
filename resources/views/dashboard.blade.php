@extends('layout.master')

@section('content')

    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    [
                        'label' => 'Dashboard',
                    ],
                ],
            ])

            @if(! ($hasRestaurantData ?? false))

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-1">Welcome back, {{ auth()->user()->name }}</h5>
                                <p class="text-muted mb-0">You don't have access to the restaurant menu data shown on this dashboard. Contact an administrator if you believe this is incorrect.</p>
                            </div>
                        </div>
                    </div>
                </div>

            @else

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-1">Welcome back, {{ auth()->user()->name }}</h5>
                                <p class="text-muted mb-0">
                                    Managing <strong>{{ $restaurant->name ?? 'House of Ramen' }}</strong>.
                                    @can('restaurant-view')
                                        <a href="{{ route('restaurant.edit') }}">Edit restaurant settings &rarr;</a>
                                    @endcan
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="mb-3 mt-4">Menu Overview</h5>
                <div class="row">
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body position-relative">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium mb-1">Menu Categories</p>
                                        <h4 class="mb-0">{{ number_format($totalCategories) }}</h4>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-primary mini-stat-icon">
                                            <span class="avatar-title rounded-circle bg-primary">
                                                <i class="bx bx-category font-size-24"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <a href="{{ route('restaurant-menu-categories.index') }}" class="stretched-link" aria-label="Manage menu categories"></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body position-relative">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium mb-1">Menu Items</p>
                                        <h4 class="mb-0">{{ number_format($totalItems) }}</h4>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-primary mini-stat-icon">
                                            <span class="avatar-title rounded-circle bg-primary">
                                                <i class="bx bx-food-menu font-size-24"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <a href="{{ route('restaurant-menu-items.index') }}" class="stretched-link" aria-label="Manage menu items"></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium mb-1">Featured Items</p>
                                        <h4 class="mb-0">{{ number_format($featuredItems) }}</h4>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-primary mini-stat-icon">
                                            <span class="avatar-title rounded-circle bg-primary">
                                                <i class="bx bx-star font-size-24"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100 {{ $itemsMissingImages > 0 ? 'border-warning' : '' }}">
                            <div class="card-body position-relative">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium mb-1">Items Missing Images</p>
                                        <h4 class="mb-0">{{ number_format($itemsMissingImages) }}</h4>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-warning mini-stat-icon">
                                            <span class="avatar-title rounded-circle bg-warning">
                                                <i class="bx bx-image-alt font-size-24"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <a href="{{ route('restaurant-menu-items.index') }}" class="stretched-link" aria-label="Manage menu items"></a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-4 col-sm-6">
                        <div class="card h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Available Items</h6>
                                    <h3 class="mb-0 text-success">{{ number_format($availableItems) }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-sm-6">
                        <div class="card h-100 {{ $unavailableItems > 0 ? 'border-warning' : '' }}">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Unavailable Items</h6>
                                    <h3 class="mb-0">{{ number_format($unavailableItems) }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-sm-6">
                        <div class="card h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Gallery Images</h6>
                                    <h3 class="mb-0">{{ number_format($galleryImages) }}</h3>
                                </div>
                                <a href="{{ route('restaurant-gallery-images.index') }}" class="btn btn-outline-primary btn-sm">Manage</a>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="mb-3 mt-4">Items By Category</h5>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="card-body">
                                @php $maxCategoryCount = max($itemsByCategory->max('menu_items_count'), 1); @endphp
                                <div class="table-responsive">
                                    <table class="table align-middle table-nowrap mb-0">
                                        <tbody>
                                        @foreach($itemsByCategory as $category)
                                            <tr>
                                                <td style="width: 35%">{{ $category->name }}</td>
                                                <td style="width: 15%">
                                                    <span class="fw-semibold">{{ number_format($category->menu_items_count) }}</span>
                                                </td>
                                                <td>
                                                    <div class="progress bg-transparent progress-sm">
                                                        <div class="progress-bar bg-primary rounded" style="width: {{ round($category->menu_items_count / $maxCategoryCount * 100) }}%"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <a href="{{ route('restaurant-menu-categories.index') }}" class="small d-inline-block mt-2">View all categories &rarr;</a>
                            </div>
                        </div>
                    </div>
                </div>

            @endif

        </div>
        <!-- container-fluid -->
    </div>
    <!-- End Page-content -->

@endsection
