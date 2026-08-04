<?php
use humhub\modules\aiops\components\Governance;
use yii\helpers\Html;
/* @var $module \humhub\modules\aiops\Module */ /* @var $llm string */ /* @var $llmAvailable bool */
?>
<div class="panel panel-default">
    <div class="panel-heading">Configuration — AI-assisted operations</div>
    <div class="panel-body">
        <?= Html::beginForm('', 'post') ?>

        <div class="alert <?= $module->isEnabled() ? 'alert-success' : 'alert-warning' ?>">
            <label>
                <input type="checkbox" name="enabled" value="1" <?= $module->isEnabled() ? 'checked' : '' ?>>
                <strong>AI layer enabled</strong>
            </label>
            <br><small>Global kill switch. When disabled, no capability runs, regardless of
            the individual switches below. Also available from the terminal:
            <code>php protected/yii aiops/kill-switch off</code></small>
        </div>

        <h4>Level 1 — autonomous</h4>
        <p class="text-muted">AI executes independently. Every enforcement here is temporary and reversible.</p>
        <?php foreach (Governance::AUTONOMOUS as $cap => $label): ?>
            <div class="checkbox"><label>
                <input type="checkbox" name="cap_<?= Html::encode($cap) ?>" value="1"
                    <?= $module->isCapabilityEnabled($cap) ? 'checked' : '' ?>>
                <?= Html::encode($label) ?> <code><?= Html::encode($cap) ?></code>
            </label></div>
        <?php endforeach; ?>

        <h4>Level 2 — proposal with human approval</h4>
        <p class="text-muted">AI only records a proposal in the queue. A person decides.</p>
        <?php foreach (Governance::PROPOSAL as $cap => $label): ?>
            <div class="checkbox"><label>
                <input type="checkbox" name="cap_<?= Html::encode($cap) ?>" value="1"
                    <?= $module->isCapabilityEnabled($cap) ? 'checked' : '' ?>>
                <?= Html::encode($label) ?> <code><?= Html::encode($cap) ?></code>
            </label></div>
        <?php endforeach; ?>

        <h4>Level 3 — human only</h4>
        <div class="alert alert-danger">
            <strong>No switch by design.</strong> These capabilities have no execution path in
            the code, so there is nothing to enable. Every attempt is rejected and recorded in
            the audit trail.
            <ul style="margin-top:8px">
            <?php foreach (Governance::HUMAN_ONLY as $cap => $label): ?>
                <li><?= Html::encode($label) ?> <code><?= Html::encode($cap) ?></code></li>
            <?php endforeach; ?>
            </ul>
        </div>

        <h4>Operational limits</h4>
        <div class="form-group">
            <label>Autonomous enforcement duration (seconds) — hard ceiling of
                <?= Governance::MAX_ENFORCEMENT_SECONDS ?>s</label>
            <input type="number" class="form-control" name="enforcement_seconds" min="300"
                   max="<?= Governance::MAX_ENFORCEMENT_SECONDS ?>"
                   value="<?= (int)$module->getEnforcementSeconds() ?>">
        </div>
        <div class="form-group">
            <label>Minimum confidence to propose (0 to 1)</label>
            <input type="number" step="0.05" min="0" max="1" class="form-control" name="min_confidence"
                   value="<?= (float)$module->getMinConfidence() ?>">
        </div>
        <div class="form-group">
            <label>Reports required to trigger escalation</label>
            <input type="number" min="1" class="form-control" name="escalation_threshold"
                   value="<?= (int)$module->getEscalationThreshold() ?>">
        </div>
        <div class="form-group">
            <label>Allowlist — users never affected by autonomous enforcement (one per line)</label>
            <textarea class="form-control" name="allowlist" rows="3"><?= Html::encode(implode("\n", $module->getAllowlist())) ?></textarea>
        </div>
        <div class="form-group">
            <label>Denylist — terms that flag content as suspicious (one per line)</label>
            <textarea class="form-control" name="denylist" rows="3"><?= Html::encode(implode("\n", $module->getDenylist())) ?></textarea>
        </div>

        <h4>Three-provider council</h4>
        <div class="alert alert-info">
            Current: <strong><?= Html::encode($llm) ?></strong>
            <?php if (!$llmAvailable): ?>
                <br>The council does not have operational quorum — the layer operates with deterministic rules only.
                Moderation remains fully functional.
            <?php endif; ?>
            <br><small>Configured through the environment, never through this screen. Each member uses
            its own <code>AIOPS_OPENAI_*</code>, <code>AIOPS_XAI_*</code> or
            <code>AIOPS_GEMINI_*</code> variables. Two distinct providers must agree.
            Secrets are never stored in the database or rendered on a page.</small>
        </div>

        <button type="submit" class="btn btn-primary">Save</button>
        <?= Html::endForm() ?>
    </div>
</div>
