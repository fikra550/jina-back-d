<?php

namespace App\Http\Controllers;

use App\Models\Utilisateur;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Log;

class UtilisateurController extends Controller
{
    public function index()
    {
        $utilisateurs = Utilisateur::nonSystem()->get();
        return response()->json($utilisateurs);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'Fullname' => 'required|string|max:255|unique:utilisateurs',
            'password' => ['required', Rules\Password::defaults()],
            'role' => 'required|in:admin,employee,superadmin',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($validated['role'] === 'superadmin' && !auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized to assign superadmin role'], 403);
        }

        $user = Utilisateur::create([
            'Fullname' => $validated['Fullname'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => $validated['status'] ?? 'active',
        ]);

        $this->syncDefaultPermissions($user);
        return response()->json($user, 201);
    }

    public function show(Utilisateur $utilisateur)
    {
        return response()->json($utilisateur);
    }

    public function update(Request $request, Utilisateur $utilisateur)
    {
        $this->authorizeUserAction($utilisateur);

        $validated = $request->validate([
            'Fullname' => 'sometimes|string|max:255|unique:utilisateurs,Fullname,' . $utilisateur->id,
            'password' => ['sometimes', Rules\Password::defaults()],
            'role' => 'sometimes|in:admin,employee,superadmin',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if (isset($validated['role']) && $validated['role'] === 'superadmin' && !auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized to assign superadmin role'], 403);
        }

        $data = $request->only(['Fullname', 'role', 'status']);
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
            $utilisateur->clearPasswordReset(); // clear reset flag
        }

        $utilisateur->update($data);

        if ($request->has('role')) {
            $this->syncDefaultPermissions($utilisateur);
        }

        return response()->json($utilisateur);
    }

    public function destroy(Utilisateur $utilisateur)
    {
        $this->authorizeUserAction($utilisateur);
        if ($utilisateur->isSuperAdmin() && !auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized to delete superadmin'], 403);
        }
        $utilisateur->delete();
        return response()->json(null, 204);
    }

    public function updateStatus(Request $request, Utilisateur $utilisateur)
    {
        $this->authorizeUserAction($utilisateur);
        $request->validate(['status' => 'required|in:active,inactive']);
        $utilisateur->update(['status' => $request->status]);
        return response()->json(['message' => 'Status updated', 'utilisateur' => $utilisateur]);
    }

    public function toggleStatus(Utilisateur $utilisateur)
    {
        $this->authorizeUserAction($utilisateur);
        $newStatus = $utilisateur->status === 'active' ? 'inactive' : 'active';
        $utilisateur->update(['status' => $newStatus]);
        return response()->json(['message' => 'Status toggled', 'utilisateur' => $utilisateur]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'Fullname' => 'sometimes|string|max:255|unique:utilisateurs,Fullname,' . $user->id,
            'password' => 'sometimes|string|min:6|confirmed',
        ]);
        if (isset($validated['Fullname'])) $user->Fullname = $validated['Fullname'];
        if (isset($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->clearPasswordReset();
        }
        $user->save();
        return response()->json(['message' => 'Profile updated', 'user' => $user]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'Fullname' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = Utilisateur::where('Fullname', $request->Fullname)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ($user->status === 'inactive') {
            return response()->json(['message' => 'Account inactive.'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'utilisateur' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // ---- Public password reset request ----
    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'Fullname' => 'required|string|exists:utilisateurs,Fullname',
            ]);

            $user = Utilisateur::where('Fullname', $request->Fullname)->first();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non trouvé.'], 404);
            }

            $user->requestPasswordReset();

            return response()->json([
                'message' => 'Demande de réinitialisation envoyée à l\'administrateur.'
            ]);
        } catch (\Exception $e) {
            Log::error('Forgot password error: ' . $e->getMessage());
            return response()->json(['message' => 'Une erreur est survenue. Veuillez réessayer.'], 500);
        }
    }

    // ---- Private helpers ----
   private function syncDefaultPermissions(Utilisateur $user): void
{
    $defaults = [
        'admin'    => ['cars', 'matricules', 'clients', 'reservations', 'accidents', 'contacts', 'users', 'lavage', 'products'],
        'employee' => ['cars', 'matricules', 'reservations', 'contacts', 'lavage', 'products'],
    ];

    $slugs = $defaults[$user->role] ?? [];
    UserPermission::where('user_id', $user->id)->whereIn('page_slug', $slugs)->delete();
    foreach ($slugs as $slug) {
        $user->grantPermission($slug, null, null);
    }
}

    private function authorizeUserAction(Utilisateur $targetUser): void
    {
        $currentUser = auth()->user();
        if ($currentUser->isEmployee()) {
            abort(403, 'Les employés ne peuvent pas modifier d\'utilisateurs.');
        }
        if ($currentUser->isAdmin() && $targetUser->isSuperAdmin()) {
            abort(403, 'Les administrateurs ne peuvent pas modifier les super-administrateurs.');
        }
    }
}