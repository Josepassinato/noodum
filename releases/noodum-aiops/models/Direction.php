<?php

namespace humhub\modules\aiops\models;

use humhub\components\ActiveRecord;

/**
 * Direcao ou ideia que o dono deu ao gerente.
 *
 * Registrar uma direcao NAO autoriza nada. A direcao entra no briefing do
 * conselho como insumo; o que muda a plataforma continua nascendo como
 * proposta e morrendo sem aprovacao humana. Isso e deliberado: o canal do
 * Telegram nao e autenticado com a forca do painel, entao ele pode informar a
 * intencao do dono, nunca executa-la.
 *
 * @property int $id
 * @property string $chat_id
 * @property string $content
 * @property string $status
 * @property string $created_at
 */
class Direction extends ActiveRecord
{
    public const STATUS_OPEN = 'open';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_ARCHIVED = 'archived';

    public static function tableName()
    {
        return 'aiops_direction';
    }

    public function rules()
    {
        return [
            [['chat_id', 'content'], 'required'],
            [['chat_id'], 'string', 'max' => 32],
            [['content'], 'string'],
            [['status'], 'in', 'range' => [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED, self::STATUS_ARCHIVED]],
            [['created_at'], 'safe'],
        ];
    }

    public static function record(string $chatId, string $content): ?self
    {
        $row = new self();
        $row->chat_id = $chatId;
        $row->content = mb_substr(trim($content), 0, 4000);
        $row->status = self::STATUS_OPEN;
        $row->created_at = gmdate('Y-m-d H:i:s');

        return $row->save(false) ? $row : null;
    }

    public static function findOpen()
    {
        return self::find()
            ->where(['status' => self::STATUS_OPEN])
            ->orderBy(['created_at' => SORT_DESC]);
    }

    /** @return self[] */
    public static function activeForBriefing(int $limit = 10): array
    {
        return self::find()
            ->where(['status' => [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED]])
            ->orderBy(['id' => SORT_DESC])
            ->limit($limit)
            ->all();
    }
}
