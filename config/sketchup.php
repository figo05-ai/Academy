<?php
return [
    // Set only to the hash of the final officially encrypted/signed RBZ.
    'release_sha256' => env('SKETCHUP_RELEASE_SHA256', 'aa48faa7b3cc6027c6f0f3eddbb0963a039a196ab7e18d5fa20b3c1bbf1f967d'),
    'device_minutes' => 5,
    'remember_days' => 30, // Same maximum as the existing Maxtree token policy.
    'lease_seconds' => 30,
];
