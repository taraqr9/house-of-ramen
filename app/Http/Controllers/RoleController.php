<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleIndexRequest;
use App\Http\Requests\RoleStoreRequest;
use App\Http\Requests\RoleUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(RoleIndexRequest $request): View
    {
        $page_title = 'Roles';

        $query = Role::query();

        if ($request->filled('keyword')) {
            $query->where('name', 'like', '%'.$request->keyword.'%');
        }

        $roles = $query->with('permissions')
            ->latest()
            ->paginate(20);

        return view('roles.index', compact('page_title', 'roles'));
    }

    public function create(): View
    {
        $page_title = 'Create Role';

        $permissions = Permission::orderBy('name')->get();

        $models = collect(File::files(app_path('Models')))
            ->map(function ($file) {
                $modelName = pathinfo($file->getFilename(), PATHINFO_FILENAME);

                return [
                    'label' => $modelName,
                    'value' => Str::snake($modelName),
                ];
            })
            ->sortBy('label')
            ->values();

        return view('roles.create', compact('page_title', 'permissions', 'models'));
    }

    public function store(RoleStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        $activity = activity()
            ->causedBy(auth()->user())
            ->performedOn($role)
            ->useLog('role')
            ->withProperties([
                'attributes' => [
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'permissions' => $role->permissions()->pluck('name')->toArray(),
                ],
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Role created');

        $activity->event = 'created';
        $activity->save();

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        $page_title = 'Edit Role';

        $permissions = Permission::orderBy('name')->get();
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        $models = collect(File::files(app_path('Models')))
            ->map(function ($file) {
                $modelName = pathinfo($file->getFilename(), PATHINFO_FILENAME);

                return [
                    'label' => $modelName,
                    'value' => Str::snake($modelName),
                ];
            })
            ->sortBy('label')
            ->values();

        return view('roles.edit', compact(
            'page_title',
            'role',
            'permissions',
            'rolePermissions',
            'models'
        ));
    }

    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        $oldData = [
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions()->pluck('name')->toArray(),
        ];

        $role->update([
            'name' => $data['name'],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        $role->refresh();

        $newData = [
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions()->pluck('name')->toArray(),
        ];

        $activity = activity()
            ->causedBy(auth()->user())
            ->performedOn($role)
            ->useLog('role')
            ->withProperties([
                'old' => $oldData,
                'attributes' => $newData,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Role updated');

        $activity->event = 'updated';
        $activity->save();

        return redirect()
            ->back()
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === 'Super Admin') {
            return redirect()
                ->route('roles.index')
                ->with('error', 'Super Admin role cannot be deleted.');
        }

        $deletedData = [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions()->pluck('name')->toArray(),
        ];

        $activity = activity()
            ->causedBy(auth()->user())
            ->performedOn($role)
            ->useLog('role')
            ->withProperties([
                'old' => $deletedData,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Role deleted');

        $activity->event = 'deleted';
        $activity->save();

        $role->delete();

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    public function storePermission(Request $request): RedirectResponse
    {
        $availableModels = collect(File::files(app_path('Models')))
            ->map(function ($file) {
                return Str::snake(pathinfo($file->getFilename(), PATHINFO_FILENAME));
            })
            ->toArray();

        $request->validate([
            'module_name' => ['required', 'string', 'in:'.implode(',', $availableModels)],
            'actions' => ['required', 'array'],
        ]);

        $moduleName = $request->module_name;

        $actions = collect($request->actions)
            ->filter(fn ($action) => ! empty(trim($action)))
            ->map(fn ($action) => strtolower(str_replace(' ', '_', trim($action))))
            ->unique();

        if ($actions->isEmpty()) {
            return redirect()
                ->back()
                ->with('error', 'Please select or enter at least one action.');
        }

        foreach ($actions as $action) {
            Permission::firstOrCreate([
                'name' => $moduleName.'-'.$action,
                'guard_name' => 'web',
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Permission created successfully.');
    }
}
