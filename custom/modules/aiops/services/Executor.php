<?php

namespace humhub\modules\aiops\services;

use humhub\modules\aiops\components\Governance;
use humhub\modules\aiops\models\AuditEntry;
use humhub\modules\aiops\models\Enforcement;
use humhub\modules\aiops\models\Proposal;
use humhub\modules\aiops\Module;
use RuntimeException;

/**
 * Unico ponto por onde a IA age.
 *
 * Nada no modulo escreve contencao ou proposta direto no banco: tudo passa por
 * aqui, porque e aqui que a governanca e verificada e a auditoria e gravada.
 * Se um caminho novo aparecer que nao passe por esta classe, a garantia de
 * governanca se perde — por isso os modelos tambem validam o nivel por conta
 * propria (defesa em profundidade).
 */
final class Executor
{
    public function __construct(private Module $module)
    {
    }

    /**
     * Executa uma capacidade de nivel 1.
     *
     * Recusa, nesta ordem: nivel diferente de 1, capacidade desligada,
     * alvo em allowlist. Toda recusa vira registro 'blocked' na auditoria —
     * saber o que a IA tentou e nao pode e tao util quanto saber o que ela fez.
     */
    public function enforce(
        string $capability,
        string $subjectType,
        int $subjectId,
        string $reason,
        array $evidence,
        string $trigger,
        ?float $confidence = null,
        ?string $subjectUsername = null
    ): ?Enforcement {
        if (!Governance::isAutonomous($capability)) {
            $this->audit($capability, $trigger, 'enforce', AuditEntry::RESULT_BLOCKED, $subjectType, $subjectId, [
                'blocked_because' => 'capacidade nao e de nivel 1',
                'level' => Governance::levelFor($capability),
            ], $confidence);

            throw new RuntimeException(
                "aiops: tentativa de execucao autonoma de capacidade nivel "
                . Governance::levelFor($capability) . " ({$capability}) recusada."
            );
        }

        if (!$this->module->isCapabilityEnabled($capability)) {
            $this->audit($capability, $trigger, 'enforce', AuditEntry::RESULT_BLOCKED, $subjectType, $subjectId, [
                'blocked_because' => 'capacidade desligada na configuracao',
            ] + $evidence, $confidence);

            return null;
        }

        if ($subjectUsername !== null && (new RulesEngine($this->module))->isAllowlisted($subjectUsername)) {
            $this->audit($capability, $trigger, 'enforce', AuditEntry::RESULT_BLOCKED, $subjectType, $subjectId, [
                'blocked_because' => 'conta em allowlist',
                'username' => $subjectUsername,
            ] + $evidence, $confidence);

            return null;
        }

        // Ja contido pela mesma regra: nao empilha nem estende o prazo.
        if (Enforcement::hasActive($subjectType, $subjectId, $capability)) {
            return null;
        }

        $enforcement = new Enforcement([
            'capability' => $capability,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'reason' => $reason,
            'evidence' => json_encode($evidence, JSON_UNESCAPED_UNICODE),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $this->module->getEnforcementSeconds()),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if (!$enforcement->save()) {
            $this->audit($capability, $trigger, 'enforce', AuditEntry::RESULT_FAILED, $subjectType, $subjectId, [
                'errors' => $enforcement->getErrors(),
            ] + $evidence, $confidence);

            return null;
        }

        $this->audit(
            $capability,
            $trigger,
            'enforce',
            AuditEntry::RESULT_EXECUTED,
            $subjectType,
            $subjectId,
            ['reason' => $reason, 'expires_at' => $enforcement->expires_at] + $evidence,
            $confidence,
            AuditEntry::ROLLBACK_PENDING
        );

        return $enforcement;
    }

    /**
     * Registra uma proposta de nivel 2 na fila humana.
     *
     * Recusa capacidade de nivel 1 (nao precisa de aprovacao) e de nivel 3 (a
     * IA nao propoe o que nao pode executar nem depois de aprovado). Tambem
     * recusa confianca abaixo do minimo — proposta fraca vira ruido e faz o
     * humano parar de ler a fila, que e o pior resultado possivel.
     */
    public function propose(
        string $capability,
        ?string $subjectType,
        ?int $subjectId,
        string $reason,
        string $proposedAction,
        string $impact,
        array $evidence,
        string $trigger,
        float $confidence
    ): ?Proposal {
        if (Governance::isHumanOnly($capability)) {
            $this->audit($capability, $trigger, 'propose', AuditEntry::RESULT_BLOCKED, $subjectType, $subjectId, [
                'blocked_because' => 'capacidade reservada a decisao humana (nivel 3)',
            ], $confidence);

            throw new RuntimeException("aiops: capacidade nivel 3 ({$capability}) nao pode ser proposta pela IA.");
        }

        if (!Governance::requiresApproval($capability)) {
            throw new RuntimeException("aiops: {$capability} nao e capacidade de nivel 2.");
        }

        if (!$this->module->isCapabilityEnabled($capability)) {
            $this->audit($capability, $trigger, 'propose', AuditEntry::RESULT_BLOCKED, $subjectType, $subjectId, [
                'blocked_because' => 'capacidade desligada na configuracao',
            ] + $evidence, $confidence);

            return null;
        }

        if ($confidence < $this->module->getMinConfidence()) {
            $this->audit($capability, $trigger, 'propose', AuditEntry::RESULT_OBSERVED, $subjectType, $subjectId, [
                'not_proposed_because' => 'confianca abaixo do minimo',
                'min_confidence' => $this->module->getMinConfidence(),
            ] + $evidence, $confidence);

            return null;
        }

        if (Proposal::hasOpenFor($capability, $subjectType, $subjectId)) {
            return null;
        }

        $proposal = new Proposal([
            'capability' => $capability,
            'status' => Proposal::STATUS_PENDING,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'reason' => $reason,
            'evidence' => json_encode($evidence, JSON_UNESCAPED_UNICODE),
            'confidence' => $confidence,
            'proposed_action' => $proposedAction,
            'impact' => $impact,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + Proposal::DEFAULT_TTL_SECONDS),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if (!$proposal->save()) {
            $this->audit($capability, $trigger, 'propose', AuditEntry::RESULT_FAILED, $subjectType, $subjectId, [
                'errors' => $proposal->getErrors(),
            ] + $evidence, $confidence);

            return null;
        }

        $entry = $this->audit(
            $capability,
            $trigger,
            'propose',
            AuditEntry::RESULT_PROPOSED,
            $subjectType,
            $subjectId,
            ['reason' => $reason, 'proposed_action' => $proposedAction] + $evidence,
            $confidence
        );
        $entry->proposal_id = $proposal->id;
        $entry->save(false);

        return $proposal;
    }

    /** Observacao pura: nao muda nada, so deixa rastro. */
    public function observe(
        string $capability,
        string $trigger,
        ?string $subjectType,
        ?int $subjectId,
        array $evidence,
        ?float $confidence = null
    ): AuditEntry {
        return $this->audit(
            $capability,
            $trigger,
            'observe',
            AuditEntry::RESULT_OBSERVED,
            $subjectType,
            $subjectId,
            $evidence,
            $confidence
        );
    }

    /**
     * Expira contencoes vencidas (housekeeping reversivel).
     * Redundante com a checagem em `isActive()`, mas mantem a tabela limpa e
     * deixa a expiracao visivel na trilha.
     */
    public function expireStaleEnforcements(): int
    {
        $expired = Enforcement::find()
            ->where(['reverted_at' => null])
            ->andWhere(['<=', 'expires_at', gmdate('Y-m-d H:i:s')])
            ->all();

        foreach ($expired as $enforcement) {
            $enforcement->revert(null, 'prazo expirado');
            $this->audit(
                'housekeeping',
                'cron',
                'expire_enforcement',
                AuditEntry::RESULT_REVERTED,
                $enforcement->subject_type,
                $enforcement->subject_id,
                ['enforcement_id' => $enforcement->id, 'capability' => $enforcement->capability],
                null,
                AuditEntry::ROLLBACK_DONE
            );
        }

        return count($expired);
    }

    /** Propostas nao decididas caducam sem executar nada. */
    public function expireStaleProposals(): int
    {
        $expired = Proposal::find()
            ->where(['status' => Proposal::STATUS_PENDING])
            ->andWhere(['<=', 'expires_at', gmdate('Y-m-d H:i:s')])
            ->all();

        foreach ($expired as $proposal) {
            $proposal->status = Proposal::STATUS_EXPIRED;
            $proposal->decided_at = gmdate('Y-m-d H:i:s');
            $proposal->save(false);

            $this->audit(
                $proposal->capability,
                'cron',
                'expire_proposal',
                AuditEntry::RESULT_REJECTED,
                $proposal->subject_type,
                $proposal->subject_id,
                ['proposal_id' => $proposal->id, 'note' => 'expirou sem decisao humana; nenhuma acao executada']
            );
        }

        return count($expired);
    }

    private function audit(
        string $capability,
        string $trigger,
        string $action,
        string $result,
        ?string $subjectType,
        ?int $subjectId,
        array $evidence,
        ?float $confidence = null,
        string $rollback = AuditEntry::ROLLBACK_NOT_APPLICABLE
    ): AuditEntry {
        return AuditEntry::record([
            'actor_type' => AuditEntry::ACTOR_AI,
            'trigger' => $trigger,
            'capability' => $capability,
            'action' => $action,
            'result' => $result,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'evidence' => json_encode($evidence, JSON_UNESCAPED_UNICODE),
            'confidence' => $confidence,
            'rollback_status' => $rollback,
        ]);
    }
}
