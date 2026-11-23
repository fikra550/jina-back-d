<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'session/*', 'login', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['https://jinabusiness.netlify.app'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];