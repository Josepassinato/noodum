<?php

namespace humhub\modules\aiops\models;

use humhub\components\ActiveRecord;
use humhub\modules\aiops\components\Governance;
use humhub\modules\user\models\User;

/**
 * Proposta de acao de nivel 2, aguardando decisao humana.
 *
 * Uma proposta nunca executa nada por conta propria. Ela vence: se ninguem
 * decidir ate `expires_at`, o estado vira 'expired' e a acao NAO acontece.
 * O silencio humano equivale a "nao", nunca a "sim".
 *
 * @property int $id
 * @property string $capability
 * @property string $status
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $reason
 * @property string|null $evidence
 * @property float|null $confidence
 * @property string $proposed_action
 * @property string|null $impact
 * @property string $expires_at
 * @property string $created_at
 * @property string|null $decided_at
 * @property int|null $decided_by
 * @property string|null $decision_note
 */
class Proposal extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    /** Prazo padrao de uma proposta. Depois disso ela caduca sem agir. */
    public const DEFAULT_TTL_SECONDS = 172800; // 48h

    public static function tableName()
    {
        return 'aiops_proposal';
    }

    public function rules()
    {
        return [
            [['capability', 'reason', 'proposed_action'], 'required'],
            [['subject_id', 'decided_by'], 'integer'],
            [['confidence'], 'number', 'min' => 0, 'max' => 1],
            [['reason', 'evidence', 'impact', 'decision_note'], 'string'],
            [['capability', 'status', 'subject_type'], 'string', 'max' => 64],
            [['proposed_action'], 'string', 'max' => 255],
            [['expires_at', 'created_at', 'decided_at'], 'safe'],
            // Barreira de esquema: so capacidade de nivel 2 vira proposta.
            // Nivel 1 nao precisa de aprovacao; nivel 3 nao pode ser executado
            // pela IA nem depois de aprovado, entao nao entra nesta fila.
            [['capability'], 'validateGovernanceLevel'],
        ];
    }

    public function validateGovernanceLevel($attribute): void
    {
        if (!Governance::requiresApproval((string)$this->$attribute)) {
            $this->addError(
                $attribute,
                'Only level 2 capabilities can become proposals. '
                . 'Received "' . $this->$attribute . '" (level ' . Governance::levelFor((string)$this->$attribute) . ').'
            );
        }
    }

    public static function findPending()
    {
        return self::find()
            ->where(['status' => self::STATUS_PENDING])
            ->orderBy(['created_at' => SORT_DESC]);
    }

    /**
     * Ja existe proposta aberta para o mesmo alvo e mesma capacidade?
     * Evita que o worker reempilhe o mesmo item a cada ciclo.
     */
    public static function hasOpenFor(string $capability, ?string $subjectType, ?int $subjectId): bool
    {
        return self::find()->where([
            'capability' => $capability,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'status' => self::STATUS_PENDING,
        ])->exists();
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING && strtotime($this->expires_at) < time();
    }

    public function getDecider(): ?User
    {
        return $this->decided_by ? User::findOne($this->decided_by) : null;
    }

    public function getGovernanceLevel(): int
    {
        return Governance::levelFor($this->capability);
    }
}
