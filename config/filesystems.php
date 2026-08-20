<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        // القرص الافتراضي
        'local' => [
            'driver'     => 'local',
            'root'       => storage_path('app/private'),
            'serve'      => true,
            'throw'      => false,
            'visibility' => 'private',
        ],

        // قرص خاص أمني للمذكرات — خارج مجلد public تماماً
        'private' => [
            'driver'     => 'local',
            'root'       => storage_path('app/private'),
            'throw'      => false,
            'visibility' => 'private',
        ],

        // public لن نستخدمه لتخزين المذكرات إطلاقاً
        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw'      => false,
        ],

    ],

    'links' => [],
];
