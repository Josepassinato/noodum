<?php
use humhub\modules\aiops\components\Governance;
use humhub\modules\aiops\models\Proposal;
use yii\helpers\Html;
use yii\helpers\Url;
/* @var $proposal Proposal */
$evidence = json_decode((string)$proposal->evidence, true) ?: [];
?>
<div class="panel panel-default">
    <div class="panel-heading">Proposal #<?= (int)$proposal->id ?> — <?= Html::encode(Governance::label($proposal->capability)) ?></div>
    <div class="panel-body">
        <table class="table table-condensed">
            <tr><th style="width:180px">Governance level</th><td><?= $proposal->getGovernanceLevel() ?> (human approval required)</td></tr>
            <tr><th>Proposed action</th><td><strong><?= Html::encode($proposal->proposed_action) ?></strong></td></tr>
            <tr><th>Reason</th><td><?= nl2br(Html::encode($proposal->reason)) ?></td></tr>
            <tr><th>Impact</th><td><?= nl2br(Html::encode((string)$proposal->impact)) ?></td></tr>
            <tr><th>Confidence</th><td><?= $proposal->confidence !== null ? round((float)$proposal->confidence * 100) . '%' : '—' ?></td></tr>
            <tr><th>Subject</th><td><?= Html::encode(($proposal->subject_type ?? '—') . ($proposal->subject_id ? ' #' . $proposal->subject_id : '')) ?></td></tr>
            <tr><th>Created at</th><td><?= Html::encode($proposal->created_at) ?> UTC</td></tr>
            <tr><th>Expires at</th><td><?= Html::encode($proposal->expires_at) ?> UTC<?= $proposal->isExpired() ? ' <span class="label label-danger">expired</span>' : '' ?></td></tr>
            <tr><th>Status</th><td><span class="label label-default"><?= Html::encode($proposal->status) ?></span></td></tr>
            <tr><th>Evidence</th><td><pre style="font-size:11px"><?= Html::encode(json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></td></tr>
        </table>

        <?php if ($proposal->status === Proposal::STATUS_PENDING && !$proposal->isExpired()): ?>
            <div class="alert alert-info">
                Approval records the decision and evidence in the audit trail. You still perform
                the concrete action in the platform's native administration screens — approval
                here never executes a change on its own.
            </div>
            <?= Html::beginForm(Url::to(['approve', 'id' => $proposal->id]), 'post') ?>
                <div class="form-group">
                    <label>Decision note (optional)</label>
                    <textarea name="note" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-success">Approve</button>
            <?= Html::endForm() ?>
            <?= Html::beginForm(Url::to(['reject', 'id' => $proposal->id]), 'post') ?>
                <button type="submit" class="btn btn-danger">Reject</button>
            <?= Html::endForm() ?>
        <?php else: ?>
            <p class="text-muted">Decided at <?= Html::encode((string)$proposal->decided_at) ?>.
                <?= $proposal->decision_note ? 'Note: ' . Html::encode($proposal->decision_note) : '' ?></p>
        <?php endif; ?>
        <p><a href="<?= Url::to(['index']) ?>">Back to the queue</a></p>
    </div>
</div>
