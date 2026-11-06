<?php
use yii\helpers\Html;
use yii\helpers\Url;
/* @var $entries \humhub\modules\aiops\models\AuditEntry[] */
/* @var $filter ?string */
?>
<div class="panel panel-default">
    <div class="panel-heading">Audit trail</div>
    <div class="panel-body">
        <p>
            <?php foreach ([null => 'all', 'observed' => 'observed', 'proposed' => 'proposed',
                'executed' => 'executed', 'blocked' => 'blocked', 'reverted' => 'reverted',
                'approved' => 'approved', 'rejected' => 'rejected', 'failed' => 'failed'] as $key => $label): ?>
                <a class="btn btn-xs <?= $filter === $key ? 'btn-primary' : 'btn-default' ?>"
                   href="<?= Url::to(['audit', 'result' => $key]) ?>"><?= Html::encode($label) ?></a>
            <?php endforeach; ?>
        </p>
        <table class="table table-condensed table-striped">
            <thead><tr>
                <th>When (UTC)</th><th>Actor</th><th>Trigger</th><th>Level</th><th>Capability</th>
                <th>Action</th><th>Result</th><th>Subject</th><th>Conf.</th><th>Rollback</th><th>Evidence</th>
            </tr></thead>
            <tbody>
            <?php foreach ($entries as $a): ?>
                <tr>
                    <td><small><?= Html::encode($a->created_at) ?></small></td>
                    <td><span class="label label-<?= $a->actor_type === 'ai' ? 'info' : 'primary' ?>"><?= Html::encode($a->actor_type) ?></span></td>
                    <td><small><?= Html::encode($a->trigger) ?></small></td>
                    <td><?= (int)$a->governance_level ?></td>
                    <td><small><?= Html::encode($a->capability) ?></small></td>
                    <td><small><?= Html::encode($a->action) ?></small></td>
                    <td><span class="label label-<?= in_array($a->result, ['blocked','failed'], true) ? 'danger'
                        : ($a->result === 'executed' ? 'success' : 'default') ?>"><?= Html::encode($a->result) ?></span></td>
                    <td><small><?= Html::encode(($a->subject_type ?? '—') . ($a->subject_id ? ' #' . $a->subject_id : '')) ?></small></td>
                    <td><?= $a->confidence !== null ? round((float)$a->confidence * 100) . '%' : '—' ?></td>
                    <td><small><?= Html::encode($a->rollback_status) ?></small></td>
                    <td><small><code style="font-size:10px"><?= Html::encode(mb_substr((string)$a->evidence, 0, 160)) ?></code></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
