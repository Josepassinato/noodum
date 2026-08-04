<?php
use humhub\modules\aiops\components\Governance;
use humhub\modules\aiops\models\Proposal;
use yii\helpers\Html;
use yii\helpers\Url;
/* @var $proposal Proposal */
$evidence = json_decode((string)$proposal->evidence, true) ?: [];
?>
<div class="panel panel-default">
    <div class="panel-heading">Proposta #<?= (int)$proposal->id ?> — <?= Html::encode(Governance::label($proposal->capability)) ?></div>
    <div class="panel-body">
        <table class="table table-condensed">
            <tr><th style="width:180px">Nivel de governanca</th><td><?= $proposal->getGovernanceLevel() ?> (aprovacao humana obrigatoria)</td></tr>
            <tr><th>Acao proposta</th><td><strong><?= Html::encode($proposal->proposed_action) ?></strong></td></tr>
            <tr><th>Motivo</th><td><?= nl2br(Html::encode($proposal->reason)) ?></td></tr>
            <tr><th>Impacto</th><td><?= nl2br(Html::encode((string)$proposal->impact)) ?></td></tr>
            <tr><th>Confianca</th><td><?= $proposal->confidence !== null ? round((float)$proposal->confidence * 100) . '%' : '—' ?></td></tr>
            <tr><th>Alvo</th><td><?= Html::encode(($proposal->subject_type ?? '—') . ($proposal->subject_id ? ' #' . $proposal->subject_id : '')) ?></td></tr>
            <tr><th>Criada em</th><td><?= Html::encode($proposal->created_at) ?> UTC</td></tr>
            <tr><th>Expira em</th><td><?= Html::encode($proposal->expires_at) ?> UTC<?= $proposal->isExpired() ? ' <span class="label label-danger">expirada</span>' : '' ?></td></tr>
            <tr><th>Situacao</th><td><span class="label label-default"><?= Html::encode($proposal->status) ?></span></td></tr>
            <tr><th>Evidencia</th><td><pre style="font-size:11px"><?= Html::encode(json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></td></tr>
        </table>

        <?php if ($proposal->status === Proposal::STATUS_PENDING && !$proposal->isExpired()): ?>
            <div class="alert alert-info">
                Aprovar registra a decisao e a evidencia na trilha de auditoria. A acao concreta
                continua sendo praticada por voce nas telas nativas da plataforma — nenhuma
                aprovacao aqui executa mudanca sozinha.
            </div>
            <?= Html::beginForm(Url::to(['approve', 'id' => $proposal->id]), 'post') ?>
                <div class="form-group">
                    <label>Observacao da decisao (opcional)</label>
                    <textarea name="note" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-success">Aprovar</button>
            <?= Html::endForm() ?>
            <?= Html::beginForm(Url::to(['reject', 'id' => $proposal->id]), 'post') ?>
                <button type="submit" class="btn btn-danger">Rejeitar</button>
            <?= Html::endForm() ?>
        <?php else: ?>
            <p class="text-muted">Decidida em <?= Html::encode((string)$proposal->decided_at) ?>.
                <?= $proposal->decision_note ? 'Observacao: ' . Html::encode($proposal->decision_note) : '' ?></p>
        <?php endif; ?>
        <p><a href="<?= Url::to(['index']) ?>">Voltar para a fila</a></p>
    </div>
</div>
