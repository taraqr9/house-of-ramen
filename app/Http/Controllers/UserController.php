<?php

namespace App\Http\Controllers;

use App\Enums\StatusEnum;
use App\Filters\UserIndexFilter;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\UserIndexRequest;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(UserIndexRequest $request): View
    {
        $page_title = 'Users';

        $roles = Role::orderBy('name')->get();

        $query = User::with('roles');

        $users = UserIndexFilter::applyFilters($query, $request)
            ->latest()
            ->paginate(20)
            ->appends($request->query());

        return view('user.index', compact(
            'page_title',
            'users',
            'roles'
        ))->with([
            'statuses' => StatusEnum::options(),
        ]);
    }

    public function create(): View
    {
        $page_title = 'Create User';

        $roles = Role::orderBy('name')->get();

        return view('user.create', compact('page_title', 'roles'));
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = $data['role'] ?? null;

        unset($data['role']);

        $data['password'] = Hash::make($data['password']);
        $data['email_verified_at'] = now();

        $user = User::create($data);

        if ($role) {
            $user->assignRole($role);
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        $page_title = 'Edit User';

        $roles = Role::orderBy('name')->get();

        $userRole = $user->roles->pluck('name')->first();

        return view('user.edit', compact(
            'page_title',
            'user',
            'roles',
            'userRole'
        ));
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $role = $data['role'] ?? null;

        unset($data['role']);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        if ($role) {
            $user->syncRoles([$role]);
        } else {
            $user->syncRoles([]);
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return redirect()
                ->route('users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        if ($user->hasRole('Super Admin')) {
            return redirect()
                ->route('users.index')
                ->with('error', 'Super Admin user cannot be deleted.');
        }

        $userName = $user->name;

        $user->syncRoles([]);
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', "User {$userName} deleted successfully.");
    }

    public function profile(): View
    {
        $page_title = 'My Profile';

        return view('user.profile', [
            'page_title' => $page_title,
            'user' => auth()->user(),
        ]);
    }

    public function updateProfile(ProfileUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = auth()->user();

        $updateData = [
            'name' => $data['name'],
        ];

        if ($request->hasFile('avatar')) {
            $updateData['avatar_path'] = $request->file('avatar')->store('users/avatars', 'public');
        }

        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
            $updateData['password_setup_token'] = null;
            $updateData['password_setup_expires_at'] = null;
            $updateData['password_changed_at'] = now();
        }

        $user->update($updateData);

        return redirect()
            ->route('profile.show')
            ->with('success', 'Profile updated successfully.');
    }

    public function impersonate(User $user): RedirectResponse
    {
        abort_unless(auth()->user()->can('user-impersonate'), 403);

        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot impersonate yourself.');
        }

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->useLog('impersonation')
            ->withProperties([
                'impersonator_id' => auth()->id(),
                'target_user_id' => $user->id,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Started impersonation');

        auth()->user()->impersonate($user);

        return redirect()
            ->route('dashboard')
            ->with('success', 'You are now impersonating '.$user->name);
    }

    public function leaveImpersonate(): RedirectResponse
    {
        $impersonatedUser = auth()->user();

        activity()
            ->causedBy($impersonatedUser)
            ->performedOn($impersonatedUser)
            ->useLog('impersonation')
            ->withProperties([
                'current_user_id' => $impersonatedUser->id,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Stopped impersonation');

        auth()->user()->leaveImpersonation();

        return redirect()
            ->route('users.index')
            ->with('success', 'You have returned to your own account.');
    }
}
