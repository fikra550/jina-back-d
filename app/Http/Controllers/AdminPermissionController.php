<?php

namespace App\Http\Controllers;

use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\PagePermissionHelper;

class AdminPermissionController extends Controller
{
    use PagePermissionHelper;

    /**
     * Get all defined pages that can be assigned.
     */

public function getPages(): JsonResponse
{
    $this->authorizePageAccess('users');

    $pages = [
        'cars'           => 'Voitures',
        'matricules'     => 'Matricules',
        'clients'        => 'Clients',
        'reservations'   => 'Réservations',
        'accidents'      => 'Accidents',
        'payments'       => 'Traites',
        'users'          => 'Utilisateurs',
        'contacts'       => 'Messages de contact',
        'reports'        => 'Rapports',
        'garages'        => 'Garages',
        'sous-locations' => 'Sous-locations',
        'lavage'         => 'Lavage',
        'products'       => 'Produits',
    ];
    return response()->json(['pages' => $pages]);
}

    /**
     * Get current permissions (with expiry) for a specific user.
     */
    public function getUserPermissions($userId): JsonResponse
    {
        $this->authorizePageAccess('users');

        $user = Utilisateur::findOrFail($userId);
        $permissions = $user->permissions()->get(['page_slug', 'expires_at', 'granted_by', 'created_at', 'updated_at']);
        return response()->json(['permissions' => $permissions]);
    }

    /**
     * Assign (or update) a time‑limited permission to a user.
     */
    public function assignPermission(Request $request): JsonResponse
    {
        $this->authorizePageAccess('users');

        $validated = $request->validate([
            'user_id'         => 'required|exists:utilisateurs,id',
            'page_slug' => 'required|string|in:cars,matricules,clients,reservations,accidents,payments,users,contacts,reports,garages,sous-locations,lavage,products',
            'duration_minutes'=> 'nullable|integer|min:1|max:43200',
        ]);

        $user = Utilisateur::findOrFail($validated['user_id']);
        $granter = $request->user();

        $permission = $user->grantPermission(
            $validated['page_slug'],
            $validated['duration_minutes'] ?? null,
            $granter
        );

        return response()->json([
            'message'    => 'Permission assigned successfully.',
            'permission' => $permission,
        ]);
    }

    /**
     * Revoke a permission.
     */
    public function revokePermission(Request $request): JsonResponse
    {
        $this->authorizePageAccess('users');

        $validated = $request->validate([
            'user_id'   => 'required|exists:utilisateurs,id',
            'page_slug' => 'required|string',
        ]);

        $user = Utilisateur::findOrFail($validated['user_id']);
        $user->revokePermission($validated['page_slug']);

        return response()->json(['message' => 'Permission revoked successfully.']);
    }

    /**
     * Get the current authenticated user's valid permissions.
     */
    public function myPermissions(Request $request): JsonResponse
{
    $user = $request->user();
    $permissions = $user->permissions()
        ->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })
        ->get(['page_slug', 'expires_at', 'granted_by', 'created_at', 'updated_at']);

    return response()->json(['permissions' => $permissions]);
}
}