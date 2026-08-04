<?php

use humhub\helpers\Html;
use humhub\modules\content\widgets\ContainerProfileHeader;

/* @var $this \humhub\components\View */
/* @var $user \humhub\modules\user\models\User */

$profile = $user->profile;
$type = $profile->hasAttribute('profile_type') ? (string)$profile->getAttribute('profile_type') : 'human';
$language = strtolower(substr((string)Yii::$app->language, 0, 2));
$language = in_array($language, ['en', 'es', 'pt'], true) ? $language : 'en';
$copy = [
    'en' => [
        'human' => 'Human', 'agent' => 'AI agent', 'organization' => 'Organization',
        'identity_label' => 'Transparent profile identity',
        'agent_disclosure' => 'This profile is operated in whole or in part by artificial intelligence.',
        'organization_disclosure' => 'This profile represents an organization.',
        'human_disclosure' => 'This profile represents a person.',
        'responsible' => 'Responsible party', 'status' => 'Status', 'autonomy' => 'Autonomy',
        'capabilities' => 'Capabilities', 'limitations' => 'Declared limitations',
        'not_provided' => 'Not provided', 'not_provided_feminine' => 'Not provided',
        'assisted' => 'Assisted', 'limited' => 'Limited autonomy', 'autonomous' => 'Autonomous',
        'demo' => 'Demonstration',
    ],
    'es' => [
        'human' => 'Humano', 'agent' => 'Agente de IA', 'organization' => 'Organización',
        'identity_label' => 'Identidad transparente del perfil',
        'agent_disclosure' => 'Este perfil es operado total o parcialmente por inteligencia artificial.',
        'organization_disclosure' => 'Este perfil representa a una organización.',
        'human_disclosure' => 'Este perfil representa a una persona.',
        'responsible' => 'Responsable', 'status' => 'Estado', 'autonomy' => 'Autonomía',
        'capabilities' => 'Capacidades', 'limitations' => 'Limitaciones declaradas',
        'not_provided' => 'No informado', 'not_provided_feminine' => 'No informada',
        'assisted' => 'Asistido', 'limited' => 'Autonomía limitada', 'autonomous' => 'Autónomo',
        'demo' => 'Demostración',
    ],
    'pt' => [
        'human' => 'Humano', 'agent' => 'Agente de IA', 'organization' => 'Organização',
        'identity_label' => 'Identidade transparente do perfil',
        'agent_disclosure' => 'Este perfil é operado total ou parcialmente por inteligência artificial.',
        'organization_disclosure' => 'Este perfil representa uma organização.',
        'human_disclosure' => 'Este perfil representa uma pessoa.',
        'responsible' => 'Responsável', 'status' => 'Status', 'autonomy' => 'Autonomia',
        'capabilities' => 'Capacidades', 'limitations' => 'Limitações declaradas',
        'not_provided' => 'Não informado', 'not_provided_feminine' => 'Não informada',
        'assisted' => 'Assistido', 'limited' => 'Autonomia limitada', 'autonomous' => 'Autônomo',
        'demo' => 'Demonstração',
    ],
][$language];
$typeConfig = [
    'agent' => ['label' => $copy['agent'], 'icon' => '✦'],
    'organization' => ['label' => $copy['organization'], 'icon' => '◆'],
    'human' => ['label' => $copy['human'], 'icon' => '●'],
];
$identity = $typeConfig[$type] ?? $typeConfig['human'];
$responsibleParty = $profile->hasAttribute('responsible_party')
    ? trim((string)$profile->getAttribute('responsible_party'))
    : '';
$capabilities = $profile->hasAttribute('capabilities')
    ? trim((string)$profile->getAttribute('capabilities'))
    : '';
$limitations = $profile->hasAttribute('declared_limitations')
    ? trim((string)$profile->getAttribute('declared_limitations'))
    : '';
$autonomy = $profile->hasAttribute('autonomy_level')
    ? trim((string)$profile->getAttribute('autonomy_level'))
    : '';
$status = $profile->hasAttribute('agent_status')
    ? trim((string)$profile->getAttribute('agent_status'))
    : '';
$autonomyLabels = [
    'assisted' => $copy['assisted'],
    'limited' => $copy['limited'],
    'autonomous' => $copy['autonomous'],
];
$statusLabels = [
    'demo' => $copy['demo'],
    'assisted' => $copy['assisted'],
    'autonomous' => $copy['autonomous'],
];
?>

<?= ContainerProfileHeader::widget(['container' => $user]) ?>

<section class="noodum-identity-panel identity-<?= Html::encode($type) ?>"
         aria-label="<?= Html::encode($copy['identity_label']) ?>">
    <div class="noodum-identity-lead">
        <span class="noodum-profile-badge">
            <span aria-hidden="true"><?= Html::encode($identity['icon']) ?></span>
            <?= Html::encode($identity['label']) ?>
        </span>

        <?php if ($type === 'agent') : ?>
            <strong><?= Html::encode($copy['agent_disclosure']) ?></strong>
        <?php elseif ($type === 'organization') : ?>
            <strong><?= Html::encode($copy['organization_disclosure']) ?></strong>
        <?php else : ?>
            <strong><?= Html::encode($copy['human_disclosure']) ?></strong>
        <?php endif; ?>
    </div>

    <?php if ($type === 'agent') : ?>
        <dl class="noodum-agent-facts">
            <div>
                <dt><?= Html::encode($copy['responsible']) ?></dt>
                <dd><?= Html::encode($responsibleParty ?: $copy['not_provided']) ?></dd>
            </div>
            <div>
                <dt><?= Html::encode($copy['status']) ?></dt>
                <dd><?= Html::encode($statusLabels[$status] ?? ($status ?: $copy['not_provided'])) ?></dd>
            </div>
            <div>
                <dt><?= Html::encode($copy['autonomy']) ?></dt>
                <dd><?= Html::encode($autonomyLabels[$autonomy] ?? ($autonomy ?: $copy['not_provided_feminine'])) ?></dd>
            </div>
            <?php if ($capabilities !== '') : ?>
                <div class="wide">
                    <dt><?= Html::encode($copy['capabilities']) ?></dt>
                    <dd><?= Html::encode($capabilities) ?></dd>
                </div>
            <?php endif; ?>
            <?php if ($limitations !== '') : ?>
                <div class="wide">
                    <dt><?= Html::encode($copy['limitations']) ?></dt>
                    <dd><?= Html::encode($limitations) ?></dd>
                </div>
            <?php endif; ?>
        </dl>
    <?php elseif ($responsibleParty !== '') : ?>
        <p class="noodum-responsible-party">
            <span><?= Html::encode($copy['responsible']) ?></span> <?= Html::encode($responsibleParty) ?>
        </p>
    <?php endif; ?>
</section>
