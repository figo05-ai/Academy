<?php

namespace App\Services\Licensing;

use App\Models\User;
use Illuminate\Support\Carbon;

class MaxtreeLicenseService
{
    /**
     * Determine whether a user is authorized and entitled to use Maxtree Tool.
     *
     * Rules:
     * 1. User must exist.
     * 2. User account must be active under global account security rules (is_active == true).
     * 3. User must have an associated Maxtree license record.
     * 4. The license must be explicitly active (is_active == true).
     * 5. If an expiration timestamp is set, it must not be in the past.
     *
     * Course enrollment / course activation is completely independent and ignored.
     */
    public function isUserLicensed(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        // Global account security check
        if (! $user->is_active) {
            return false;
        }

        $license = $user->maxtreeLicense;

        if ($license === null) {
            return false;
        }

        // License explicit active status check (default-deny)
        if (! $license->is_active) {
            return false;
        }

        // Expiration check
        if ($license->expires_at !== null && $license->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
