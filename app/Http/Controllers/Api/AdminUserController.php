<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $query = User::query()->with(['roles', 'properties']);

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json([
            'data' => $query->orderBy('name')->paginate($request->integer('per_page', 25)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required', 'string', Password::defaults()],
            'phone' => 'nullable|string|max:32',
            'job_title' => 'nullable|string|max:120',
            'default_property_id' => 'nullable|exists:properties,id',
            'is_active' => 'boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
            'property_ids' => 'nullable|array',
            'property_ids.*' => 'exists:properties,id',
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'default_property_id' => $data['default_property_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (! empty($data['property_ids'])) {
            $user->properties()->sync($data['property_ids']);
        }

        $this->audit->log('created', $user, null, $user->toArray());

        return response()->json(['data' => $user->fresh(['roles', 'properties'])], 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:190',
            'email' => 'sometimes|required|email|max:190|unique:users,email,'.$user->id,
            'password' => ['nullable', 'string', Password::defaults()],
            'phone' => 'nullable|string|max:32',
            'job_title' => 'nullable|string|max:120',
            'default_property_id' => 'nullable|exists:properties,id',
            'is_active' => 'boolean',
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $old = $user->toArray();
        $user->update($data);

        $this->audit->log('updated', $user, $old, $user->toArray());

        return response()->json(['data' => $user->fresh(['roles', 'properties'])]);
    }

    public function assignRoles(Request $request, User $user)
    {
        $data = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $user->syncRoles($data['roles']);

        $this->audit->log('roles_assigned', $user, null, ['roles' => $data['roles']]);

        return response()->json(['data' => $user->fresh('roles')]);
    }

    public function assignProperties(Request $request, User $user)
    {
        $data = $request->validate([
            'property_ids' => 'required|array',
            'property_ids.*' => 'exists:properties,id',
            'default_property_id' => 'nullable|exists:properties,id',
        ]);

        $user->properties()->sync($data['property_ids']);

        if (! empty($data['default_property_id'])) {
            $user->update(['default_property_id' => $data['default_property_id']]);
        }

        $this->audit->log('properties_assigned', $user, null, ['properties' => $data['property_ids']]);

        return response()->json(['data' => $user->fresh('properties')]);
    }
}
