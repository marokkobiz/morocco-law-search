<?php

return [
    'paths' => [
        resource_path('views'),
    ],

    'compiled' => storage_path(env('VIEW_COMPILED_PATH', 'framework/views')),
];
