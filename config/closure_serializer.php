<?php

/**
 * ClosureSerializer configuration.
 *
 * ╔════════════════════════════════════════════════════════════════════╗
 * ║  WARNING — REMOTE CODE EXECUTION SURFACE                          ║
 * ║                                                                    ║
 * ║  ClosureSerializer::deserialize() executes PHP source code via    ║
 * ║  eval(). It is gated behind:                                       ║
 * ║                                                                    ║
 * ║    1. This `enabled` flag (defaults to FALSE).                    ║
 * ║    2. An HMAC signature over the payload, verified with APP_KEY    ║
 * ║       in constant time before eval() runs.                         ║
 * ║                                                                    ║
 * ║  Even with both locks, deserialize() is RCE if APP_KEY leaks.     ║
 * ║  Do not enable this unless you have a hard product requirement     ║
 * ║  and you've thought about that threat model.                       ║
 * ╚════════════════════════════════════════════════════════════════════╝
 */

return [

    /*
    | Master switch. Setting this to false makes deserialize() throw on every
    | call regardless of signature validity, so a leaked or partially-stolen
    | payload can never reach eval() on a host where the feature isn't wanted.
    |
    | Override per environment via env('CLOSURE_SERIALIZER_ENABLED').
    */
    'enabled' => filter_var(env('CLOSURE_SERIALIZER_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

];
