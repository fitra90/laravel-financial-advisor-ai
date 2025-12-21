<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/'),
    'request_timeout' => env('GEMINI_REQUEST_TIMEOUT', 30),
];