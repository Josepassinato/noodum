<?php

use humhub\modules\aiops\components\Governance;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $module \humhub\modules\aiops\Module */
/* @var $health array */
/* @var $digest array */
/* @var $pending \humhub\modules\aiops\models\Proposal[] */
/* @var $pendingCount int */
/* @var $enforcements \humhub\modules\aiops\models\Enforcement[] */
/* @var $compliance array */
/* @var $recentAudit \humhub\modules\aiops\models\AuditEntry[] */
?>
<div class="panel panel-default">
    <div class="panel-heading">Operacao assistida por IA</div>
    <div class="panel-body">

        <div class="alert <?= $module->isEnabled() ? 'alert-success' : 'alert-warning' ?>">
            <strong>Camada de IA:
                <?= $module->isEnabled() ? 'LIGADA' : 'DESLIGADA (kill switch)' ?></strong>
            &nbsp;·&nbsp; Modelo: <?= Html::encode($health['llm']) ?>
            <?php if (!$health['llm_available']): ?>
                <br><small>Sem provedor de modelo configurado — a camada roda apenas com regras
                    deterministicas. Moderacao e governanca continuam valendo integralmente.</small>
            <?php endif; ?>
            <a class="btn btn-default btn-xs pull-right" href="<?= Url::to(['/aiops/settings']) ?>">Configurar</a>
        </div>

        <h4>Saude da plataforma</h4>
        <table class="table table-condensed">
            <tr>
                <td>Usuarios ativos</td><td><strong><?= (int)$health['users_total'] ?></strong></td>
                <td>Publicacoes (24h)</td><td><strong><?= (int)$health['content_24h'] ?></strong></td>
                <td>Comunidades</td><td><strong><?= (int)$health['spaces_total'] ?></strong></td>
            </tr>
            <tr>
                <td>Denuncias abertas</td><td><strong><?= (int)$health['reports_open'] ?></strong></td>
                <td>Contencoes ativas</td><td><strong><?= (int)$health['enforcements_active'] ?></strong></td>
                <td>Eventos auditados (24h)</td><td><strong><?= (int)$health['audit_24h'] ?></strong></td>
            </tr>
        </table>

        <h4>Conformidade de identidade dos perfis</h4>
        <p>
            <span class="label label-<?= $compliance['actionable'] > 0 ? 'danger' : 'success' ?>">
                <?= (int)$compliance['actionable'] ?> acionaveis
            </span>
            <span class="label label-warning"><?= (int)$compliance['in_grace'] ?> em carencia</span>
            <span class="label label-default"><?= (int)$compliance['total_issues'] ?> pendencias no total</span>
        </p>

        <h4>Fila de aprovacao humana — nivel 2
            <?php if ($pendingCount > 0): ?>
                <span class="label label-warning"><?= (int)$pendingCount ?> pendentes</span>
            <?php endif; ?>
        </h4>
        <?php if ($pending === []): ?>
            <p class="text-muted">Nenhuma proposta aguardando decisao.</p>
        <?php else: ?>
            <table class="table table-condensed table-hover">
                <thead><tr><th>Capacidade</th><th>Motivo</th><th>Confianca</th><th>Expira</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pending as $p): ?>
                    <tr>
                        <td><?= Html::encode(Governance::label($p->capability)) ?></td>
                        <td><small><?= Html::encode(mb_substr($p->reason, 0, 120)) ?></small></td>
                        <td><?= $p->confidence !== null ? round((float)$p->confidence * 100) . '%' : '—' ?></td>
                        <td><small><?= Html::encode($p->expires_at) ?></small></td>
                        <td><a class="btn btn-xs btn-primary"
                               href="<?= Url::to(['/aiops/approval/view', 'id' => $p->id]) ?>">Revisar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <a href="<?= Url::to(['/aiops/approval']) ?>">Ver fila completa</a>
        <?php endif; ?>

        <h4>Contencoes ativas — nivel 1 (temporarias e reversiveis)</h4>
        <?php if ($enforcements === []): ?>
            <p class="text-muted">Nenhuma contencao ativa.</p>
        <?php else: ?>
            <table class="table table-condensed">
                <thead><tr><th>Capacidade</th><th>Alvo</th><th>Motivo</th><th>Expira</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($enforcements as $e): ?>
                    <tr>
                        <td><?= Html::encode(Governance::label($e->capability)) ?></td>
                        <td><?= Html::encode($e->subject_type . ' #' . $e->subject_id) ?></td>
                        <td><small><?= Html::encode(mb_substr($e->reason, 0, 100)) ?></small></td>
                        <td><small><?= Html::encode($e->expires_at) ?></small></td>
                        <td>
                            <?= Html::beginForm(Url::to(['/aiops/dashboard/revert', 'id' => $e->id]), 'post') ?>
                            <button type="submit" class="btn btn-xs btn-default">Reverter agora</button>
                            <?= Html::endForm() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h4>Resumo operacional</h4>
        <pre style="white-space:pre-wrap"><?= Html::encode((new humhub\modules\aiops\services\Digest(
            $module,
            new humhub\modules\aiops\services\OperationsManager($module)
        ))->render($digest)) ?></pre>

        <h4>Trilha de auditoria — ultimos eventos</h4>
        <table class="table table-condensed">
            <thead><tr><th>Quando</th><th>Ator</th><th>Nivel</th><th>Capacidade</th><th>Acao</th><th>Resultado</th></tr></thead>
            <tbody>
            <?php foreach ($recentAudit as $a): ?>
                <tr>
                    <td><small><?= Html::encode($a->created_at) ?></small></td>
                    <td><span class="label label-<?= $a->actor_type === 'ai' ? 'info' : 'primary' ?>">
                            <?= Html::encode($a->actor_type) ?></span></td>
                    <td><?= (int)$a->governance_level ?></td>
                    <td><small><?= Html::encode($a->capability) ?></small></td>
                    <td><small><?= Html::encode($a->action) ?></small></td>
                    <td><span class="label label-<?= in_array($a->result, ['blocked', 'failed'], true) ? 'danger'
                            : ($a->result === 'executed' ? 'success' : 'default') ?>">
                            <?= Html::encode($a->result) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <a href="<?= Url::to(['/aiops/dashboard/audit']) ?>">Ver trilha completa</a>
    </div>
</div>
