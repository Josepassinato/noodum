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
    <div class="panel-heading">AI-assisted operations</div>
    <div class="panel-body">

        <div class="alert <?= $module->isEnabled() ? 'alert-success' : 'alert-warning' ?>">
            <strong>AI layer:
                <?= $module->isEnabled() ? 'ENABLED' : 'DISABLED (kill switch)' ?></strong>
            &nbsp;·&nbsp; Model: <?= Html::encode($health['llm']) ?>
            <?php if (!$health['llm_available']): ?>
                <br><small>No model provider is configured — the layer runs with deterministic
                    rules only. Moderation and governance remain fully enforced.</small>
            <?php endif; ?>
            <a class="btn btn-default btn-xs pull-right" href="<?= Url::to(['/aiops/settings']) ?>">Configure</a>
        </div>

        <h4>Platform health</h4>
        <table class="table table-condensed">
            <tr>
                <td>Active users</td><td><strong><?= (int)$health['users_total'] ?></strong></td>
                <td>Publications (24h)</td><td><strong><?= (int)$health['content_24h'] ?></strong></td>
                <td>Communities</td><td><strong><?= (int)$health['spaces_total'] ?></strong></td>
            </tr>
            <tr>
                <td>Open reports</td><td><strong><?= (int)$health['reports_open'] ?></strong></td>
                <td>Active enforcements</td><td><strong><?= (int)$health['enforcements_active'] ?></strong></td>
                <td>Audited events (24h)</td><td><strong><?= (int)$health['audit_24h'] ?></strong></td>
            </tr>
        </table>

        <h4>Profile identity compliance</h4>
        <p>
            <span class="label label-<?= $compliance['actionable'] > 0 ? 'danger' : 'success' ?>">
                <?= (int)$compliance['actionable'] ?> actionable
            </span>
            <span class="label label-warning"><?= (int)$compliance['in_grace'] ?> in grace period</span>
            <span class="label label-default"><?= (int)$compliance['total_issues'] ?> total issues</span>
        </p>

        <h4>Human approval queue — level 2
            <?php if ($pendingCount > 0): ?>
                <span class="label label-warning"><?= (int)$pendingCount ?> pending</span>
            <?php endif; ?>
        </h4>
        <?php if ($pending === []): ?>
            <p class="text-muted">No proposals are awaiting a decision.</p>
        <?php else: ?>
            <table class="table table-condensed table-hover">
                <thead><tr><th>Capability</th><th>Reason</th><th>Confidence</th><th>Expires</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pending as $p): ?>
                    <tr>
                        <td><?= Html::encode(Governance::label($p->capability)) ?></td>
                        <td><small><?= Html::encode(mb_substr($p->reason, 0, 120)) ?></small></td>
                        <td><?= $p->confidence !== null ? round((float)$p->confidence * 100) . '%' : '—' ?></td>
                        <td><small><?= Html::encode($p->expires_at) ?></small></td>
                        <td><a class="btn btn-xs btn-primary"
                               href="<?= Url::to(['/aiops/approval/view', 'id' => $p->id]) ?>">Review</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <a href="<?= Url::to(['/aiops/approval']) ?>">View full queue</a>
        <?php endif; ?>

        <h4>Active enforcements — level 1 (temporary and reversible)</h4>
        <?php if ($enforcements === []): ?>
            <p class="text-muted">No active enforcements.</p>
        <?php else: ?>
            <table class="table table-condensed">
                <thead><tr><th>Capability</th><th>Subject</th><th>Reason</th><th>Expires</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($enforcements as $e): ?>
                    <tr>
                        <td><?= Html::encode(Governance::label($e->capability)) ?></td>
                        <td><?= Html::encode($e->subject_type . ' #' . $e->subject_id) ?></td>
                        <td><small><?= Html::encode(mb_substr($e->reason, 0, 100)) ?></small></td>
                        <td><small><?= Html::encode($e->expires_at) ?></small></td>
                        <td>
                            <?= Html::beginForm(Url::to(['/aiops/dashboard/revert', 'id' => $e->id]), 'post') ?>
                            <button type="submit" class="btn btn-xs btn-default">Revert now</button>
                            <?= Html::endForm() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h4>Operational summary</h4>
        <pre style="white-space:pre-wrap"><?= Html::encode((new humhub\modules\aiops\services\Digest(
            $module,
            new humhub\modules\aiops\services\OperationsManager($module)
        ))->render($digest)) ?></pre>

        <h4>Audit trail — latest events</h4>
        <table class="table table-condensed">
            <thead><tr><th>When</th><th>Actor</th><th>Level</th><th>Capability</th><th>Action</th><th>Result</th></tr></thead>
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
        <a href="<?= Url::to(['/aiops/dashboard/audit']) ?>">View full audit trail</a>
    </div>
</div>
