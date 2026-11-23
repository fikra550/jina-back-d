<?php

namespace App\Traits;

trait PagePermissionHelper
{
    /**
     * Authorize access to a page for the current user.
     * Superadmin always allowed. Others must have explicit permission.
     */
    protected function authorizePageAccess(string $pageSlug): void
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        if (!$user->hasValidPermission($pageSlug)) {
            abort(403, "Accès refusé. Vous n'avez pas la permission pour {$pageSlug}.");
        }
    }
}