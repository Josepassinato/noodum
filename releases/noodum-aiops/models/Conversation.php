<?php

namespace humhub\modules\aiops\models;

use humhub\components\ActiveRecord;

/**
 * Uma fala do canal do dono — dele ou do gerente.
 *
 * Existe para o gerente ter fio da meada entre mensagens. Nao e trilha de
 * auditoria: o que a IA observou, propos ou fez continua em `aiops_audit`.
 *
 * @property int $id
 * @property string $chat_id
 * @property string $role
 * @property string $content
 * @property string $created_at
 */
class Conversation extends ActiveRecord
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_MANAGER = 'manager';

    /** Quantas falas o gerente carrega como contexto. */
    public const CONTEXT_TURNS = 12;

    /** Alem disto o historico e podado, para nao crescer sem limite. */
    public const RETAINED_TURNS = 200;

    public static function tableName()
    {
        return 'aiops_conversation';
    }

    public function rules()
    {
        return [
            [['chat_id', 'role', 'content'], 'required'],
            [['chat_id'], 'string', 'max' => 32],
            [['role'], 'in', 'range' => [self::ROLE_OWNER, self::ROLE_MANAGER]],
            [['content'], 'string'],
            [['created_at'], 'safe'],
        ];
    }

    public static function remember(string $chatId, string $role, string $content): void
    {
        $row = new self();
        $row->chat_id = $chatId;
        $row->role = $role;
        $row->content = mb_substr(trim($content), 0, 8000);
        $row->created_at = gmdate('Y-m-d H:i:s');
        $row->save(false);
    }

    /**
     * Ultimas falas em ordem cronologica.
     *
     * @return self[]
     */
    public static function recentFor(string $chatId, int $limit = self::CONTEXT_TURNS): array
    {
        $rows = self::find()
            ->where(['chat_id' => $chatId])
            ->orderBy(['id' => SORT_DESC])
            ->limit($limit)
            ->all();

        return array_reverse($rows);
    }

    /** Mantem a memoria curta: o canal e operacional, nao um arquivo historico. */
    public static function prune(string $chatId): void
    {
        $cutoff = self::find()
            ->select('id')
            ->where(['chat_id' => $chatId])
            ->orderBy(['id' => SORT_DESC])
            ->offset(self::RETAINED_TURNS)
            ->limit(1)
            ->scalar();

        if ($cutoff !== false && $cutoff !== null) {
            self::deleteAll(['and', ['chat_id' => $chatId], ['<=', 'id', (int)$cutoff]]);
        }
    }
}
