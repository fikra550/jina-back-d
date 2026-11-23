<?php
// app/Http/Controllers/RoleController.php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user->is_super_admin && !$user->hasPermission('view_roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $roles = Role::with('permissions')->get();
        
        return response()->json(['roles' => $roles]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user->is_super_admin && !$user->hasPermission('create_roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|unique:roles',
            'slug' => 'required|string|unique:roles',
            'description' => 'nullable|string',
            'level' => 'required|integer|min:1|max:5',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        try {
            DB::beginTransaction();

            $role = Role::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'level' => $validated['level'],
                'is_system' => false
            ]);

            if (isset($validated['permissions'])) {
                $role->permissions()->sync($validated['permissions']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Role created successfully',
                'role' => $role->load('permissions')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create role: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create role: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user->is_super_admin && !$user->hasPermission('view_roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = Role::with('permissions', 'users')->findOrFail($id);
        
        return response()->json(['role' => $role]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user->is_super_admin && !$user->hasPermission('edit_roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = Role::findOrFail($id);

        if ($role->is_system && !$user->is_super_admin) {
            return response()->json(['message' => 'Cannot modify system roles'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|unique:roles,name,' . $id,
            'slug' => 'sometimes|string|unique:roles,slug,' . $id,
            'description' => 'nullable|string',
            'level' => 'sometimes|integer|min:1|max:5',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        try {
            DB::beginTransaction();

            $role->update($validated);

            if (isset($validated['permissions'])) {
                $role->permissions()->sync($validated['permissions']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Role updated successfully',
                'role' => $role->load('permissions')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update role: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update role: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user->is_super_admin && !$user->hasPermission('delete_roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['message' => 'Cannot delete system roles'], 403);
        }

        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete role with assigned users'
            ], 422);
        }

        try {
            $role->permissions()->detach();
            $role->delete();

            return response()->json(['message' => 'Role deleted successfully']);

        } catch (\Exception $e) {
            Log::error('Failed to delete role: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete role: ' . $e->getMessage()
            ], 500);
        }
    }

    public function assignRoleToUser(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user->is_super_admin && !$user->hasPermission('assign_roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:utilisateurs,id',
            'role_id' => 'required|exists:roles,id',
            'valid_until' => 'nullable|date|after:now',
            'assignment_reason' => 'nullable|string'
        ]);

        $targetUser = Utilisateur::findOrFail($validated['user_id']);
        $role = Role::findOrFail($validated['role_id']);

        if (!$user->canAssignRole($role)) {
            return response()->json([
                'message' => 'You cannot assign a role with higher or equal priority'
            ], 403);
        }

        if ($targetUser->is_super_admin) {
            return response()->json([
                'message' => 'Cannot modify super admin roles'
            ], 403);
        }

        try {
            $targetUser->assignRole(
                $role,
                $validated['valid_until'] ?? null,
                $user->Fullname,
                $validated['assignment_reason'] ?? null
            );

            return response()->json([
                'message' => 'Role assigned successfully',
                'user' => $targetUser->load('roles')
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to assign role: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to assign role: ' . $e->getMessage()
            ], 500);
        }
    }

    public function removeRoleFromUser(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user->is_super_admin && !$user->hasPermission('assign_roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:utilisateurs,id',
            'role_id' => 'required|exists:roles,id'
        ]);

        $targetUser = Utilisateur::findOrFail($validated['user_id']);
        $role = Role::findOrFail($validated['role_id']);

        if ($targetUser->is_super_admin) {
            return response()->json([
                'message' => 'Cannot modify super admin roles'
            ], 403);
        }

        try {
            $targetUser->removeRole($role);

            return response()->json([
                'message' => 'Role removed successfully',
                'user' => $targetUser->load('roles')
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to remove role: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to remove role: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateRoleValidity(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user->is_super_admin && !$user->hasPermission('assign_roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:utilisateurs,id',
            'role_id' => 'required|exists:roles,id',
            'valid_until' => 'nullable|date'
        ]);

        $targetUser = Utilisateur::findOrFail($validated['user_id']);
        $role = Role::findOrFail($validated['role_id']);

        if ($targetUser->is_super_admin) {
            return response()->json([
                'message' => 'Cannot modify super admin roles'
            ], 403);
        }

        try {
            $targetUser->updateRoleValidity($role->id, $validated['valid_until']);

            return response()->json([
                'message' => 'Role validity updated successfully',
                'user' => $targetUser->load('roles')
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update role validity: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update role validity: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getUserRoles($userId): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user->is_super_admin && !$user->hasPermission('view_users')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $targetUser = Utilisateur::findOrFail($userId);
        
        $roles = $targetUser->roles()->withPivot('valid_from', 'valid_until', 'is_active', 'assigned_by', 'assignment_reason')->get();
        
        return response()->json([
            'user' => $targetUser,
            'roles' => $roles,
            'current_role' => $targetUser->current_role
        ]);
    }

    public function getAllPermissions(): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user->is_super_admin && !$user->hasPermission('view_roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $permissions = Permission::all()->groupBy('category');
        
        return response()->json(['permissions' => $permissions]);
    }

    public function getExpiringRoles(): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user->is_super_admin && !$user->hasPermission('view_users')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $expiringSoon = DB::table('role_user')
            ->where('valid_until', '>', now())
            ->where('valid_until', '<=', now()->addDays(7))
            ->where('is_active', true)
            ->get();

        $result = [];
        foreach ($expiringSoon as $assignment) {
            $targetUser = Utilisateur::find($assignment->user_id);
            $role = Role::find($assignment->role_id);
            
            if ($targetUser && $role) {
                $result[] = [
                    'user' => $targetUser,
                    'role' => $role,
                    'expires_at' => $assignment->valid_until,
                    'days_left' => now()->diffInDays($assignment->valid_until)
                ];
            }
        }

        return response()->json(['expiring_assignments' => $result]);
    }
}