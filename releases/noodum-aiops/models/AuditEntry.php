<?php

namespace humhub\modules\aiops\models;

use humhub\components\ActiveRecord;
use humhub\modules\aiops\components\Governance;
use Yii;

/**
 * Trilha de auditoria da camada de IA.
 *
 * Regra: escreve-se aqui ANTES de agir, nunca depois. Se a acao falhar, o
 * registro fica com result='failed'. Uma acao que aconteceu sem deixar rastro
 * seria pior do que a acao nao ter acontecido, entao a ordem importa.
 *
 * @property int $id
 * @property string $actor_type
 * @property int|null $actor_user_id
 * @property string $trigger
 * @property string $capability
 * @property int $governance_level
 * @property string $action
 * @property string $result
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $evidence
 * @property float|null $confidence
 * @property string $rollback_status
 * @property int|null $reviewer_user_id
 * @property int|null $proposal_id
 * @property string $created_at
 */
class AuditEntry extends ActiveRecord
{
    public const ACTOR_AI = 'ai';
    public const ACTOR_HUMAN = 'human';

    public const RESULT_OBSERVED = 'observed';
    public const RESULT_PROPOSED = 'proposed';
    public const RESULT_EXECUTED = 'executed';
    public const RESULT_BLOCKED = 'blocked';
    public const RESULT_FAILED = 'failed';
    public const RESULT_REVERTED = 'reverted';
    public const RESULT_APPROVED = 'approved';
    public const RESULT_REJECTED = 'rejected';

    public const ROLLBACK_NOT_APPLICABLE = 'not_applicable';
    public const ROLLBACK_PENDING = 'reversible';
    public const ROLLBACK_DONE = 'reverted';

    public static function tableName()
    {
        return 'aiops_audit';
    }

    public function rules()
    {
        return [
            [['trigger', 'capability', 'action', 'result'], 'required'],
            [['actor_user_id', 'subject_id', 'reviewer_user_id', 'proposal_id', 'governance_level'], 'integer'],
            [['confidence'], 'number', 'min' => 0, 'max' => 1],
            [['evidence'], 'string'],
            [['actor_type', 'trigger', 'capability', 'action', 'result', 'subject_type', 'rollback_status'], 'string', 'max' => 64],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * Grava uma entrada. O nivel de governanca nunca vem do chamador: e
     * derivado da capacidade, para que a trilha nao possa ser rotulada com um
     * nivel mais brando do que o real.
     */
    public static function record(array $attributes): self
    {
        $entry = new self();
        $entry->setAttributes($attributes, false);
        $entry->governance_level = Governance::levelFor((string)($attributes['capability'] ?? ''));
        $entry->actor_type = $attributes['actor_type'] ?? self::ACTOR_AI;
        $entry->rollback_status = $attributes['rollback_status'] ?? self::ROLLBACK_NOT_APPLICABLE;
        $entry->created_at = gmdate('Y-m-d H:i:s');

        if (!$entry->save()) {
            Yii::error('aiops: failed to record audit entry: ' . json_encode($entry->getErrors()), 'aiops');
        }

        return $entry;
    }

    public function getEvidenceData(): array
    {
        if ($this->evidence === null || $this->evidence === '') {
            return [];
        }

        $decoded = json_decode($this->evidence, true);

        return is_array($decoded) ? $decoded : ['raw' => $this->evidence];
    }
}
