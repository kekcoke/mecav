<?php

return [
    'python_service_host' => env('GRPC_PYTHON_SERVICE_HOST', 'python-service:50051'),
    'insecure'            => env('GRPC_INSECURE', true),
    'service_token'       => env('GRPC_SERVICE_TOKEN', ''),
    'timeout_ms'          => env('GRPC_TIMEOUT_MS', 30000),
];
