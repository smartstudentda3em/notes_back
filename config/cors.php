<?php

return [

    'paths' => ['api/*', 'login', 'logout', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // عنوان واجهة React (Vite) — عدّله حسب بيئتك
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // نعرّض ترويسات النطاق ليقرأها العميل عند التحميل على أجزاء (طباعة الملفات الكبيرة)
    'exposed_headers' => ['Content-Range', 'Accept-Ranges', 'Content-Length'],

    'max_age' => 0,

    // نستخدم توكن Bearer وليس كوكيز، لذا لا حاجة لـ credentials
    'supports_credentials' => false,
];
