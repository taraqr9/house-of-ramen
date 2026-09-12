@php
    /**
     * Determine whether a menu item should be active.
     *
     * Priority:
     * 1. Use active_routes from database when configured.
     * 2. Otherwise, derive resource wildcard from the route.
     *    Example: salary-grades.index => salary-grades.*
     * 3. If the route is not an index route, use the exact route name.
     */
    $isMenuRouteActive = function ($menuItem): bool {
        $patterns = collect($menuItem->active_routes ?? [])
            ->filter(function ($pattern) {
                return is_string($pattern) && trim($pattern) !== '';
            })
            ->map(function ($pattern) {
                return trim($pattern);
            })
            ->unique()
            ->values()
            ->all();

        if (empty($patterns) && !empty($menuItem->route)) {
            if (str($menuItem->route)->endsWith('.index')) {
                $routeBase = str($menuItem->route)
                    ->beforeLast('.')
                    ->toString();

                $patterns[] = $routeBase . '.*';
            } else {
                $patterns[] = $menuItem->route;
            }
        }

        if (empty($patterns)) {
            return false;
        }

        return request()->routeIs(...$patterns);
    };

    /**
     * Generate menu URL.
     */
    $getMenuUrl = function ($menuItem): string {
        if (
            !empty($menuItem->route)
            && Route::has($menuItem->route)
        ) {
            return route($menuItem->route);
        }

        if (!empty($menuItem->url)) {
            return url($menuItem->url);
        }

        return '#';
    };

    /**
     * Check menu permission.
     */
    $canViewMenu = function ($menuItem): bool {
        return empty($menuItem->permission)
            || auth()->user()->can($menuItem->permission);
    };
@endphp

<div class="vertical-menu">
    <div data-simplebar class="h-100">
        <div id="sidebar-menu">

            <ul class="metismenu list-unstyled"
                id="side-menu">

                <li class="menu-title">
                    Menu
                </li>

                @forelse($menus as $menu)

                    @php
                        /*
                         * Only retain child menus that:
                         * - are active
                         * - have no permission, or the user has permission
                         */
                        $visibleChildren = $menu->children
                            ->filter(function ($child) use ($canViewMenu) {
                                $isEnabled = true;

                                if ($child->is_active instanceof \BackedEnum) {
                                    $isEnabled = (int) $child->is_active->value === 1;
                                } elseif ($child->is_active !== null) {
                                    $isEnabled = (int) $child->is_active === 1;
                                }

                                return $isEnabled
                                    && $canViewMenu($child);
                            })
                            ->sortBy('serial')
                            ->values();

                        $hasVisibleChildren = $visibleChildren->isNotEmpty();

                        $menuUrl = $getMenuUrl($menu);

                        /*
                         * Parent becomes active when:
                         * - its own route matches, or
                         * - any visible child route matches.
                         */
                        $isParentRouteActive = $isMenuRouteActive($menu);

                        $isChildActive = $hasVisibleChildren
                            && $visibleChildren->contains(
                                function ($child) use ($isMenuRouteActive) {
                                    return $isMenuRouteActive($child);
                                }
                            );

                        $isActive = $isParentRouteActive
                            || $isChildActive;

                        $canViewParent = $canViewMenu($menu);

                        $parentIsEnabled = true;

                        if ($menu->is_active instanceof \BackedEnum) {
                            $parentIsEnabled =
                                (int) $menu->is_active->value === 1;
                        } elseif ($menu->is_active !== null) {
                            $parentIsEnabled =
                                (int) $menu->is_active === 1;
                        }
                    @endphp

                    @if($canViewParent && $parentIsEnabled)

                        @if($hasVisibleChildren)

                            <li class="{{ $isActive ? 'mm-active' : '' }}">

                                <a href="javascript:void(0);"
                                   class="has-arrow waves-effect {{ $isActive ? 'mm-active' : '' }}"
                                   aria-expanded="{{ $isActive ? 'true' : 'false' }}">

                                    @if(!empty($menu->icon))
                                        <i class="{{ $menu->icon }}"></i>
                                    @endif

                                    <span>
                                        {{ $menu->title }}
                                    </span>
                                </a>

                                <ul class="sub-menu {{ $isActive ? 'mm-show' : '' }}"
                                    aria-expanded="{{ $isActive ? 'true' : 'false' }}">

                                    @foreach($visibleChildren as $child)

                                        @php
                                            $childUrl = $getMenuUrl($child);

                                            /*
                                             * This now uses database-driven
                                             * active_routes.
                                             */
                                            $childActive = $isMenuRouteActive(
                                                $child
                                            );
                                        @endphp

                                        <li class="{{ $childActive ? 'mm-active' : '' }}">

                                            <a href="{{ $childUrl }}"
                                               class="{{ $childActive ? 'active' : '' }}"
                                               aria-current="{{ $childActive ? 'page' : 'false' }}">

                                                @if(!empty($child->icon))
                                                    <i class="{{ $child->icon }}"></i>
                                                @endif

                                                <span>
                                                    {{ $child->title }}
                                                </span>
                                            </a>

                                        </li>

                                    @endforeach

                                </ul>

                            </li>

                        @elseif(
                            !empty($menu->route)
                            || !empty($menu->url)
                        )

                            <li class="{{ $isActive ? 'mm-active' : '' }}">

                                <a href="{{ $menuUrl }}"
                                   class="waves-effect {{ $isActive ? 'active' : '' }}"
                                   aria-current="{{ $isActive ? 'page' : 'false' }}">

                                    @if(!empty($menu->icon))
                                        <i class="{{ $menu->icon }}"></i>
                                    @endif

                                    <span>
                                        {{ $menu->title }}
                                    </span>
                                </a>

                            </li>

                        @endif

                    @endif

                @empty

                    <li class="text-muted px-3 py-2">
                        No menu found
                    </li>

                @endforelse

            </ul>

        </div>
    </div>
</div>
