<?php

return [
    'pdf' => [
        'enabled' => true,
        'binary'  => '/usr/bin/wkhtmltopdf', // 👈 cambia por la ruta que te dio "which wkhtmltopdf"
        'timeout' => false,
        'options' => [],
        'env'     => [],
    ],
    'image' => [
        'enabled' => true,
        'binary'  => '/usr/bin/wkhtmltoimage', // idem para imágenes
        'timeout' => false,
        'options' => [],
        'env'     => [],
    ],
];
