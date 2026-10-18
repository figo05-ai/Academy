<?php
namespace App\Services\Licensing;
use App\Models\User;
class SketchupLicenseService {
    public function error(?User $user): ?string {
        if (!$user || !$user->is_active) return 'ACCOUNT_DISABLED';
        $license = $user->sketchupLicense()->first();
        if (!$license) return 'LICENSE_NOT_FOUND';
        if (!$license->is_active) return 'LICENSE_INACTIVE';
        if ($license->expires_at && $license->expires_at->lessThanOrEqualTo(now())) return 'LICENSE_EXPIRED';
        return null;
    }
}
