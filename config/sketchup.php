<?php
return [
    // Set only to the hash of the final officially encrypted/signed RBZ.
    'release_sha256' => env('SKETCHUP_RELEASE_SHA256', '01a630c199713f53bbf21d3e48611b3e14b5cec856a20170b0e690d8760c0dae'),
    'device_minutes' => 5,
    'remember_days' => 30, // Same maximum as the existing Maxtree token policy.
    'lease_seconds' => 30,
];
