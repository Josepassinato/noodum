<?php

return [
    'controllerMap' => [
        'installer' => [
            'class' => humhub\modules\installer\commands\InstallController::class,
        ],
        'human-agent-bootstrap' => [
            'class' => app\commands\BootstrapController::class,
        ],
    ],
];
