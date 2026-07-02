<?php

return [

    /*
    | JWT secret key. Use a strong random string in production.
    |
    | For key rotation, set this to an array. The FIRST element is used to
    | sign new tokens; every element is tried during verification, so tokens
    | signed by older keys keep working until they expire.
    |
    |   'secret' => [
    |       env('JWT_SECRET'),
    |       env('JWT_SECRET_PREVIOUS'),
    |   ],
    */
    'secret' => env('JWT_SECRET', 'your-secret-key-change-this-in-production'),

    /*
    | Default token TTL in seconds. Override per-call in JWT::encode().
    */
    'expiration' => (int) env('JWT_EXPIRATION', 3600),

    /*
    | Refresh token TTL in seconds.
    */
    'refresh_expiration' => (int) env('JWT_REFRESH_EXPIRATION', 604800),

    /*
    | Signing algorithm. ONLY this value is honored — the alg field in the
    | token header is ignored at verification time to avoid algorithm
    | confusion / "alg: none" attacks.
    |
    | Supported: HS256, HS384, HS512.
    */
    'algorithm' => env('JWT_ALGORITHM', 'HS256'),

    /*
    | Optional iss claim. When set, JWT::decode() requires the token's `iss`
    | to equal this. Empty string = unchecked.
    */
    'issuer' => env('JWT_ISSUER', ''),

    /*
    | Optional aud claim. When set, JWT::decode() requires the token's `aud`
    | to equal (or contain, if the token's aud is an array) this value.
    | Empty string = unchecked.
    */
    'audience' => env('JWT_AUDIENCE', ''),

    /*
    | Clock-skew leeway in seconds. nbf/exp/iat checks tolerate this much
    | drift in either direction. 30s is a reasonable default for systems
    | that aren't using a synchronized clock source.
    */
    'leeway' => (int) env('JWT_LEEWAY', 30),

];
