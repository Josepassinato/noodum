<?php

namespace humhub\modules\aiops\models;

use humhub\components\ActiveRecord;
use humhub\modules\aiops\components\Governance;

/**
 * Contencao autonoma (nivel 1): limite de taxa ou quarentena.
 *
 * Duas garantias que o resto do modulo depende:
 *  - Sempre expira. `expires_at` e NOT NULL e limitado por
 *    Governance::MAX_ENFORCEMENT_SECONDS, entao mesmo que o worker pare de
 *    rodar a contencao morre sozinha ao ser consultada.
 *  - Sempre reversivel. Nada aqui apaga conteudo ou conta; apenas marca um
 *    estado temporario que `isActive()` deixa de reconhecer.
 *
 * @property int $id
 * @property string $capability
 * @property string $subject_type
 * @property int $subject_id
 * @property string $reason
 * @property string|null $evidence
 * @property string $expires_at
 * @property string $created_at
 * @property string|null $reverted_at
 * @property int|null $reverted_by
 * @property string|null $revert_reason
 */
class Enforcement extends ActiveRecord
{
    public const SUBJECT_USER = 'user';
    public const SUBJECT_CONTENT = 'content';

    public static function tableName()
    {
        return 'aiops_enforcement';
    }

    public function rules()
    {
        return [
            [['capability', 'subject_type', 'subject_id', 'reason', 'expires_at'], 'required'],
            [['subject_id', 'reverted_by'], 'integer'],
            [['reason', 'evidence'], 'string'],
            [['capability', 'subject_type'], 'string', 'max' => 64],
            [['revert_reason'], 'string', 'max' => 255],
            [['expires_at', 'created_at', 'reverted_at'], 'safe'],
            [['capability'], 'validateAutonomous'],
            [['expires_at'], 'validateBounded'],
        ];
    }

    /** Contencao autonoma so pode existir para capacidade de nivel 1. */
    public function validateAutonomous($attribute): void
    {
        if (!Governance::isAutonomous((string)$this->$attribute)) {
            $this->addError($attribute, 'Contencao autonoma exige capacidade de nivel 1.');
        }
    }

    /** Nenhuma contencao pode durar mais que o teto — nem por configuracao. */
    public function validateBounded($attribute): void
    {
        $expiry = strtotime((string)$this->$attribute);
        if ($expiry === false) {
            $this->addError($attribute, 'Prazo invalido.');
            return;
        }
        if ($expiry > time() + Governance::MAX_ENFORCEMENT_SECONDS) {
            $this->addError($attribute, 'Prazo excede o teto de ' . Governance::MAX_ENFORCEMENT_SECONDS . 's.');
        }
    }

    /**
     * Vale agora? Expiracao e avaliada na leitura, nao por job — assim a
     * contencao se desfaz sozinha mesmo com o worker parado.
     */
    public function isActive(): bool
    {
        return $this->reverted_at === null && strtotime($this->expires_at) > time();
    }

    public static function activeFor(string $subjectType, int $subjectId): array
    {
        return self::find()
            ->where(['subject_type' => $subjectType, 'subject_id' => $subjectId, 'reverted_at' => null])
            ->andWhere(['>', 'expires_at', gmdate('Y-m-d H:i:s')])
            ->all();
    }

    public static function hasActive(string $subjectType, int $subjectId, ?string $capability = null): bool
    {
        $query = self::find()
            ->where(['subject_type' => $subjectType, 'subject_id' => $subjectId, 'reverted_at' => null])
            ->andWhere(['>', 'expires_at', gmdate('Y-m-d H:i:s')]);

        if ($capability !== null) {
            $query->andWhere(['capability' => $capability]);
        }

        return $query->exists();
    }

    public static function findActive()
    {
        return self::find()
            ->where(['reverted_at' => null])
            ->andWhere(['>', 'expires_at', gmdate('Y-m-d H:i:s')])
            ->orderBy(['created_at' => SORT_DESC]);
    }

    public function revert(?int $userId, string $reason): bool
    {
        $this->reverted_at = gmdate('Y-m-d H:i:s');
        $this->reverted_by = $userId;
        $this->revert_reason = mb_substr($reason, 0, 255);

        return $this->save(false);
    }
}
