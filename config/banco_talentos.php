<?php

return [
    'retencao_anos' => 2,
    'upload_max_kb' => 10240,
    'minimo_caracteres_texto' => 80,

    'energia' => [
        'habilitada' => (bool) env('ENERGIA_IA_HABILITADA', false),
        'timeout' => (int) env('ENERGIA_IA_TIMEOUT', 20),
    ],
];
