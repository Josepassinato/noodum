<?php
use humhub\modules\aiops\components\Governance;
use humhub\modules\aiops\models\Proposal;
use yii\helpers\Html;
use yii\helpers\Url;
/* @var $proposals Proposal[] */ /* @var $status string */ /* @var $pendingCount int */
?>
<div class="panel panel-default">
    <div class="panel-heading">Fila de aprovacao — governanca nivel 2</div>
    <div class="panel-body">
        <p class="text-muted">
            A IA descreve, evidencia e propoe. A decisao e humana. Proposta que expira sem
            decisao <strong>nao executa nada</strong> — silencio equivale a "nao".
        </p>
        <p>
            <?php foreach (['pending' => 'pendentes', 'approved' => 'aprovadas', 'rejected' => 'rejeitadas',
                'expired' => 'expiradas', 'all' => 'todas'] as $key => $label): ?>
                <a class="btn btn-xs <?= $status === $key ? 'btn-primary' : 'btn-default' ?>"
                   href="<?= Url::to(['index', 'status' => $key]) ?>"><?= Html::encode($label) ?></a>
            <?php endforeach; ?>
        </p>
        <?php if ($proposals === []): ?>
            <p class="text-muted">Nenhuma proposta nesta situacao.</p>
        <?php else: ?>
        <table class="table table-condensed table-hover">
            <thead><tr><th>Criada</th><th>Capacidade</th><th>Acao proposta</th><th>Alvo</th>
                <th>Confianca</th><th>Situacao</th><th>Expira</th><th></th></tr></thead>
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
                    <td><a class="btn btn-xs btn-primary" href="<?= Url::to(['view', 'id' => $p->id]) ?>">Revisar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
