<?php

namespace humhub\modules\aiops\services;

use humhub\modules\user\models\User;
use Yii;

/**
 * Conformidade dos perfis de agente.
 *
 * O contrato da rede e que todo perfil operado por IA seja reconhecivel e
 * tenha alguem respondendo por ele. Esta classe verifica exatamente isso e nada
 * alem — nao julga conteudo, so identidade.
 *
 * Perfil fora de conformidade e sinalizado e, passado o periodo de carencia,
 * colocado em quarentena temporaria. Nunca apagado: apagar seria irreversivel,
 * o que e nivel 3, e alem disso o caminho de conserto e o dono preencher o
 * campo que falta — coisa que uma conta apagada nao permite.
 */
final class AgentCompliance
{
    /** Carencia antes de conter um perfil recem-criado e incompleto. */
    public const GRACE_SECONDS = 259200; // 72h

    /** Campos que um perfil de agente precisa ter preenchidos. */
    private const REQUIRED_AGENT_FIELDS = [
        'responsible_party' => 'responsavel humano ou institucional',
        'declared_limitations' => 'limitacoes declaradas',
        'autonomy_level' => 'nivel de autonomia',
        'agent_status' => 'situacao do agente',
    ];

    /**
     * Avalia todos os perfis e devolve os problemas encontrados.
     *
     * @return array<int,array{user_id:int,username:string,issues:string[],in_grace:bool,profile_type:?string}>
     */
    public function scan(): array
    {
        if (Yii::$app->db->getTableSchema('profile', true) === null) {
            return [];
        }

        $columns = array_keys(Yii::$app->db->getTableSchema('profile')->columns);
        if (!in_array('profile_type', $columns, true)) {
            return []; // instalacao sem identidade transparente configurada
        }

        $select = ['u.id', 'u.username', 'u.created_at', 'p.profile_type'];
        foreach (array_keys(self::REQUIRED_AGENT_FIELDS) as $field) {
            if (in_array($field, $columns, true)) {
                $select[] = 'p.' . $field;
            }
        }

        $rows = Yii::$app->db->createCommand(
            'SELECT ' . implode(', ', $select) . '
             FROM user u LEFT JOIN profile p ON p.user_id = u.id
             WHERE u.status = :status',
            [':status' => User::STATUS_ENABLED]
        )->queryAll();

        $findings = [];
        foreach ($rows as $row) {
            $issues = $this->issuesFor($row, $columns);
            if ($issues === []) {
                continue;
            }

            $createdAt = strtotime((string)($row['created_at'] ?? '')) ?: 0;
            $findings[] = [
                'user_id' => (int)$row['id'],
                'username' => (string)$row['username'],
                'profile_type' => $row['profile_type'] ?? null,
                'issues' => $issues,
                'in_grace' => $createdAt > 0 && (time() - $createdAt) < self::GRACE_SECONDS,
            ];
        }

        return $findings;
    }

    /**
     * @param array<string,mixed> $row
     * @param string[] $columns
     * @return string[]
     */
    private function issuesFor(array $row, array $columns): array
    {
        $issues = [];
        $type = $row['profile_type'] ?? null;

        // Perfil sem tipo nenhum: a rede nao consegue rotular a identidade,
        // que e justamente a promessa central.
        if ($type === null || $type === '') {
            $issues[] = 'perfil sem tipo de identidade definido';
            return $issues;
        }

        if ($type !== 'agent') {
            // Organizacao tambem precisa de responsavel, por regra do produto.
            if ($type === 'organization' && $this->isBlank($row, 'responsible_party', $columns)) {
                $issues[] = 'organizacao sem responsavel declarado';
            }
            return $issues;
        }

        foreach (self::REQUIRED_AGENT_FIELDS as $field => $label) {
            if ($this->isBlank($row, $field, $columns)) {
                $issues[] = 'agente sem ' . $label;
            }
        }

        return $issues;
    }

    private function isBlank(array $row, string $field, array $columns): bool
    {
        if (!in_array($field, $columns, true)) {
            return true; // coluna ausente = requisito nao atendido
        }

        return !isset($row[$field]) || trim((string)$row[$field]) === '';
    }

    /**
     * Resumo para o painel: quantos perfis conformes, em carencia e vencidos.
     *
     * @return array{total_issues:int,in_grace:int,actionable:int}
     */
    public function summary(): array
    {
        $findings = $this->scan();
        $inGrace = 0;
        foreach ($findings as $finding) {
            if ($finding['in_grace']) {
                $inGrace++;
            }
        }

        return [
            'total_issues' => count($findings),
            'in_grace' => $inGrace,
            'actionable' => count($findings) - $inGrace,
        ];
    }
}
