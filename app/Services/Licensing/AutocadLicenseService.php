<?php

namespace App\Services\Licensing;

use App\Models\User;

class AutocadLicenseService
{
    /**
     * Determine whether a user is authorized and entitled to use the AutoCAD Tool.
     *
     * Rules:
     * 1. User must exist.
     * 2. User account must be active (is_active == true).
     * 3. User must have an associated Autocad license record.
     * 4. The license must be explicitly active (is_active == true).
     * 5. If an expiration timestamp is set, it must not be in the past.
     */
    public function isUserLicensed(?User $user): bool
    {
        return $this->error($user) === null;
    }

    /**
     * Return error code string if licensing fails, or null if valid.
     */
    public function error(?User $user): ?string
    {
        if (! $user || ! $user->is_active) {
            return 'ACCOUNT_DISABLED';
        }

        $license = $user->autocadLicense()->first();

        if (! $license) {
            return 'LICENSE_NOT_FOUND';
        }

        if (! $license->is_active) {
            return 'LICENSE_INACTIVE';
        }

        if ($license->expires_at !== null && $license->expires_at->lessThanOrEqualTo(now())) {
            return 'LICENSE_EXPIRED';
        }

        return null;
    }
}
