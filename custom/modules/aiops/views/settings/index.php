<?php
use humhub\modules\aiops\components\Governance;
use yii\helpers\Html;
/* @var $module \humhub\modules\aiops\Module */ /* @var $llm string */ /* @var $llmAvailable bool */
?>
<div class="panel panel-default">
    <div class="panel-heading">Configuracao — Operacao assistida por IA</div>
    <div class="panel-body">
        <?= Html::beginForm('', 'post') ?>

        <div class="alert <?= $module->isEnabled() ? 'alert-success' : 'alert-warning' ?>">
            <label>
                <input type="checkbox" name="enabled" value="1" <?= $module->isEnabled() ? 'checked' : '' ?>>
                <strong>Camada de IA ligada</strong>
            </label>
            <br><small>Interruptor geral. Desligado, nenhuma capacidade roda, independentemente
            dos interruptores individuais abaixo. Tambem disponivel pelo terminal:
            <code>php protected/yii aiops/kill-switch off</code></small>
        </div>

        <h4>Nivel 1 — autonomo</h4>
        <p class="text-muted">A IA executa sozinha. Toda contencao aqui e temporaria e reversivel.</p>
        <?php foreach (Governance::AUTONOMOUS as $cap => $label): ?>
            <div class="checkbox"><label>
                <input type="checkbox" name="cap_<?= Html::encode($cap) ?>" value="1"
                    <?= $module->isCapabilityEnabled($cap) ? 'checked' : '' ?>>
                <?= Html::encode($label) ?> <code><?= Html::encode($cap) ?></code>
            </label></div>
        <?php endforeach; ?>

        <h4>Nivel 2 — proposta com aprovacao humana</h4>
        <p class="text-muted">A IA so registra na fila. Quem decide e uma pessoa.</p>
        <?php foreach (Governance::PROPOSAL as $cap => $label): ?>
            <div class="checkbox"><label>
                <input type="checkbox" name="cap_<?= Html::encode($cap) ?>" value="1"
                    <?= $module->isCapabilityEnabled($cap) ? 'checked' : '' ?>>
                <?= Html::encode($label) ?> <code><?= Html::encode($cap) ?></code>
            </label></div>
        <?php endforeach; ?>

        <h4>Nivel 3 — exclusivo humano</h4>
        <div class="alert alert-danger">
            <strong>Sem interruptor, por construcao.</strong> Estas capacidades nao possuem
            caminho de execucao no codigo: nao ha o que ligar. Qualquer tentativa de executa-las
            e recusada e registrada na trilha de auditoria.
            <ul style="margin-top:8px">
            <?php foreach (Governance::HUMAN_ONLY as $cap => $label): ?>
                <li><?= Html::encode($label) ?> <code><?= Html::encode($cap) ?></code></li>
            <?php endforeach; ?>
            </ul>
        </div>

        <h4>Limites operacionais</h4>
        <div class="form-group">
            <label>Duracao de contencao autonoma (segundos) — teto rigido de
                <?= Governance::MAX_ENFORCEMENT_SECONDS ?>s</label>
            <input type="number" class="form-control" name="enforcement_seconds" min="300"
                   max="<?= Governance::MAX_ENFORCEMENT_SECONDS ?>"
                   value="<?= (int)$module->getEnforcementSeconds() ?>">
        </div>
        <div class="form-group">
            <label>Confianca minima para propor (0 a 1)</label>
            <input type="number" step="0.05" min="0" max="1" class="form-control" name="min_confidence"
                   value="<?= (float)$module->getMinConfidence() ?>">
        </div>
        <div class="form-group">
            <label>Denuncias que disparam escalonamento</label>
            <input type="number" min="1" class="form-control" name="escalation_threshold"
                   value="<?= (int)$module->getEscalationThreshold() ?>">
        </div>
        <div class="form-group">
            <label>Allowlist — usuarios nunca alcancados por contencao autonoma (um por linha)</label>
            <textarea class="form-control" name="allowlist" rows="3"><?= Html::encode(implode("\n", $module->getAllowlist())) ?></textarea>
        </div>
        <div class="form-group">
            <label>Denylist — termos que marcam conteudo como suspeito (um por linha)</label>
            <textarea class="form-control" name="denylist" rows="3"><?= Html::encode(implode("\n", $module->getDenylist())) ?></textarea>
        </div>

        <h4>Provedor de modelo</h4>
        <div class="alert alert-info">
            Atual: <strong><?= Html::encode($llm) ?></strong>
            <?php if (!$llmAvailable): ?>
                <br>Sem provedor configurado — a camada opera so com regras deterministicas.
                Nada de moderacao deixa de funcionar por isso.
            <?php endif; ?>
            <br><small>Configurado por ambiente, nunca por esta tela:
            <code>AIOPS_LLM_PROVIDER</code>, <code>AIOPS_LLM_API_KEY</code>,
            <code>AIOPS_LLM_BASE_URL</code>, <code>AIOPS_LLM_MODEL</code>.
            Segredo nao entra em banco nem em pagina.</small>
        </div>

        <button type="submit" class="btn btn-primary">Salvar</button>
        <?= Html::endForm() ?>
    </div>
</div>
