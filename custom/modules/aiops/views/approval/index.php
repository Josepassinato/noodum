<?php
use humhub\modules\aiops\components\Governance;
use humhub\modules\aiops\models\Proposal;
use yii\helpers\Html;
use yii\helpers\Url;
/* @var $proposals Proposal[] */ /* @var $status string */ /* @var $pendingCount int */
?>
<div class="panel panel-default">
    <div class="panel-heading">Approval queue — level 2 governance</div>
    <div class="panel-body">
        <p class="text-muted">
            AI describes, supports with evidence and proposes. A human decides. A proposal
            that expires without a decision <strong>executes nothing</strong> — silence means no.
        </p>
        <p>
            <?php foreach (['pending' => 'pending', 'approved' => 'approved', 'rejected' => 'rejected',
                'expired' => 'expired', 'all' => 'all'] as $key => $label): ?>
                <a class="btn btn-xs <?= $status === $key ? 'btn-primary' : 'btn-default' ?>"
                   href="<?= Url::to(['index', 'status' => $key]) ?>"><?= Html::encode($label) ?></a>
            <?php endforeach; ?>
        </p>
        <?php if ($proposals === []): ?>
            <p class="text-muted">No proposals match this status.</p>
        <?php else: ?>
        <table class="table table-condensed table-hover">
            <thead><tr><th>Created</th><th>Capability</th><th>Proposed action</th><th>Subject</th>
                <th>Confidence</th><th>Status</th><th>Expires</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($proposals as $p): ?>
                <tr>
                    <td><small><?= Html::encode($p->created_at) ?></small></td>
                    <td><?= Html::encode(Governance::label($p->capability)) ?></td>
                    <td><small><?= Html::encode($p->proposed_action) ?></small></td>
                    <td><small><?= Html::encode(($p->subject_type ?? '—') . ($p->subject_id ? ' #' . $p->subject_id : '')) ?></small></td>
                    <td><?= $p->confidence !== null ? round((float)$p->confidence * 100) . '%' : '—' ?></td>
                    <td><span class="label label-<?= $p->status === 'pending' ? 'warning'
                        : ($p->status === 'approved' ? 'success' : 'default') ?>"><?= Html::encode($p->status) ?></span></td>
                    <td><small><?= Html::encode($p->expires_at) ?></small></td>
                    <td><a class="btn btn-xs btn-primary" href="<?= Url::to(['view', 'id' => $p->id]) ?>">Review</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
