<?php
// app/Http/Controllers/AdminRoleController.php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminRoleController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:super-admin']);
    }

    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_roles' => Role::count(),
            'total_users' => Utilisateur::nonSystem()->count(),
            'total_permissions' => Permission::count(),
            'active_assignments' => DB::table('role_user')->where('is_active', true)->count(),
            'expiring_soon' => DB::table('role_user')
                ->where('valid_until', '>', now())
                ->where('valid_until', '<=', now()->addDays(7))
                ->count(),
            'expired_assignments' => DB::table('role_user')
                ->where('valid_until', '<', now())
                ->where('is_active', true)
                ->count()
        ];

        return response()->json(['stats' => $stats]);
    }

    public function createSuperAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'Fullname' => 'required|string|unique:utilisateurs',
            'password' => 'required|string|min:6',
            'email' => 'nullable|email'
        ]);

        try {
            $user = Utilisateur::create([
                'Fullname' => $validated['Fullname'],
                'password' => Hash::make($validated['password']),
                'status' => 'active',
                'is_super_admin' => true,
                'role' => 'admin'
            ]);

            // Assign super-admin role
            $superAdminRole = Role::where('slug', 'super-admin')->first();
            if ($superAdminRole) {
                $user->assignRole($superAdminRole, null, 'System', 'Super Admin creation');
            }

            return response()->json([
                'message' => 'Super Admin created successfully',
                'user' => $user
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create Super Admin: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAuditLog(): JsonResponse
    {
        $auditLog = DB::table('role_user')
            ->join('utilisateurs', 'role_user.user_id', '=', 'utilisateurs.id')
            ->join('roles', 'role_user.role_id', '=', 'roles.id')
            ->select(
                'role_user.*',
                'utilisateurs.Fullname as user_name',
                'roles.name as role_name'
            )
            ->orderBy('role_user.created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json(['audit_log' => $auditLog]);
    }

    public function bulkAssignRoles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:utilisateurs,id',
            'role_id' => 'required|exists:roles,id',
            'valid_until' => 'nullable|date',
            'assignment_reason' => 'nullable|string'
        ]);

        $user = auth()->user();
        $role = Role::findOrFail($validated['role_id']);

        if (!$user->canAssignRole($role)) {
            return response()->json([
                'message' => 'You cannot assign this role'
            ], 403);
        }

        $results = [];
        foreach ($validated['user_ids'] as $userId) {
            $targetUser = Utilisateur::find($userId);
            if ($targetUser && !$targetUser->is_super_admin) {
                try {
                    $targetUser->assignRole(
                        $role,
                        $validated['valid_until'] ?? null,
                        $user->Fullname,
                        $validated['assignment_reason'] ?? null
                    );
                    $results[] = ['user_id' => $userId, 'status' => 'success'];
                } catch (\Exception $e) {
                    $results[] = ['user_id' => $userId, 'status' => 'error', 'message' => $e->getMessage()];
                }
            } else {
                $results[] = ['user_id' => $userId, 'status' => 'skipped', 'message' => 'Cannot modify super admin'];
            }
        }

        return response()->json([
            'message' => 'Bulk assignment completed',
            'results' => $results
        ]);
    }
}