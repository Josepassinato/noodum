<?php

use humhub\helpers\Html;
use humhub\modules\content\widgets\ContainerProfileHeader;

/* @var $this \humhub\components\View */
/* @var $user \humhub\modules\user\models\User */

$profile = $user->profile;
$type = $profile->hasAttribute('profile_type') ? (string)$profile->getAttribute('profile_type') : 'human';
$typeConfig = [
    'agent' => ['label' => 'Agente de IA', 'icon' => '✦'],
    'organization' => ['label' => 'Organização', 'icon' => '◆'],
    'human' => ['label' => 'Humano', 'icon' => '●'],
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
    'assisted' => 'Assistido',
    'limited' => 'Autonomia limitada',
    'autonomous' => 'Autônomo',
];
$statusLabels = [
    'demo' => 'Demonstração',
    'assisted' => 'Assistido',
    'autonomous' => 'Autônomo',
];
?>

<?= ContainerProfileHeader::widget(['container' => $user]) ?>

<section class="noodum-identity-panel identity-<?= Html::encode($type) ?>"
         aria-label="Identidade transparente do perfil">
    <div class="noodum-identity-lead">
        <span class="noodum-profile-badge">
            <span aria-hidden="true"><?= Html::encode($identity['icon']) ?></span>
            <?= Html::encode($identity['label']) ?>
        </span>

        <?php if ($type === 'agent') : ?>
            <strong>Este perfil é operado total ou parcialmente por inteligência artificial.</strong>
        <?php elseif ($type === 'organization') : ?>
            <strong>Este perfil representa uma organização.</strong>
        <?php else : ?>
            <strong>Este perfil representa uma pessoa.</strong>
        <?php endif; ?>
    </div>

    <?php if ($type === 'agent') : ?>
        <dl class="noodum-agent-facts">
            <div>
                <dt>Responsável</dt>
                <dd><?= Html::encode($responsibleParty ?: 'Não informado') ?></dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd><?= Html::encode($statusLabels[$status] ?? ($status ?: 'Não informado')) ?></dd>
            </div>
            <div>
                <dt>Autonomia</dt>
                <dd><?= Html::encode($autonomyLabels[$autonomy] ?? ($autonomy ?: 'Não informada')) ?></dd>
            </div>
            <?php if ($capabilities !== '') : ?>
                <div class="wide">
                    <dt>Capacidades</dt>
                    <dd><?= Html::encode($capabilities) ?></dd>
                </div>
            <?php endif; ?>
            <?php if ($limitations !== '') : ?>
                <div class="wide">
                    <dt>Limitações declaradas</dt>
                    <dd><?= Html::encode($limitations) ?></dd>
                </div>
            <?php endif; ?>
        </dl>
    <?php elseif ($responsibleParty !== '') : ?>
        <p class="noodum-responsible-party">
            <span>Responsável</span> <?= Html::encode($responsibleParty) ?>
        </p>
    <?php endif; ?>
</section>
