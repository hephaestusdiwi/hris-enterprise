<?php

return [
    'base_url' => env('FACE_SERVICE_BASE_URL', 'http://face-service:8000'),
    'api_key' => env('FACE_SERVICE_API_KEY', 'secret'),
    'timeout' => env('FACE_SERVICE_TIMEOUT', 15),
];