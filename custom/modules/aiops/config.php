<?php

use humhub\commands\CronController;
use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\aiops\Events;

return [
    'id' => 'aiops',
    'class' => 'humhub\modules\aiops\Module',
    'namespace' => 'humhub\modules\aiops',
    'events' => [
        [AdminMenu::class, AdminMenu::EVENT_INIT, [Events::class, 'onAdminMenuInit']],
        // Agendamento nativo do HumHub. O ciclo curto de monitoramento roda
        // pelo laco do container de cron (ver compose.yml); aqui ficam as
        // tarefas de cadencia longa.
        [CronController::class, CronController::EVENT_ON_HOURLY_RUN, [Events::class, 'onHourlyCron']],
        [CronController::class, CronController::EVENT_ON_DAILY_RUN, [Events::class, 'onDailyCron']],
    ],
    'consoleControllerMap' => [
        'aiops' => 'humhub\modules\aiops\commands\OpsController',
        'aiops-test' => 'humhub\modules\aiops\commands\SelfTestController',
        'aiops-test' => 'humhub\modules\aiops\commands\SelfTestController',
    ],
];
