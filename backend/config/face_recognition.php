<?php

return [
    'base_url' => env('FACE_SERVICE_BASE_URL', 'http://face-service:8000'),
    'api_key' => env('FACE_SERVICE_API_KEY', 'secret'),
    'timeout' => env('FACE_SERVICE_TIMEOUT', 15),

    // Harus selalu sinkron dengan FACE_MODEL_NAME di face-service.
    // Dipakai untuk memfilter kandidat wajah supaya tidak membandingkan
    // embedding dari model yang berbeda (mis. ArcFace lama vs SFace baru).
    'active_model' => env('FACE_ACTIVE_MODEL', 'SFace'),
];