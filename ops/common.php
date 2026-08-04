<?php

$config = [
    'components' => [
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
        ],
    ],
    'params' => [
        'installed' => true,
    ],
];

$smtpDsn = trim((string)getenv('SMTP_DSN'));

if ($smtpDsn !== '') {
    $config['components']['mailer'] = [
        'class' => \humhub\components\mail\Mailer::class,
        'transport' => ['dsn' => $smtpDsn],
    ];

    // Keep credentials outside HumHub's database and prevent the admin UI from
    // silently replacing the environment-managed transport.
    $config['params']['fixed-settings']['base'] = [
        'mailerTransportType' => 'config',
        'mailerSystemEmailAddress' => getenv('HUMHUB_SITE_EMAIL') ?: 'noreply@example.invalid',
        'mailerSystemEmailName' => getenv('HUMHUB_SITE_NAME') ?: 'NOODUM',
    ];
}

return $config;
