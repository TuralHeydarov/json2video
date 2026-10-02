<?php

return [
    'enabled' => env('SUPABASE_AUTH_ENABLED', false),
    'client_id' => env('SUPABASE_OAUTH_CLIENT_ID', ''),
    'client_secret' => env('SUPABASE_OAUTH_CLIENT_SECRET', ''),
    'callback' => env('SUPABASE_OAUTH_CALLBACK', 'https://json2video.tural.ai/shared/callback'),
    'issuer' => env('SUPABASE_AUTH_ISSUER', 'https://id.tural.ai/auth/v1'),
];
