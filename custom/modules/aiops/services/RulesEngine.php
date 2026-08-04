<?php

namespace humhub\modules\aiops\services;

use humhub\modules\aiops\components\Sanitizer;
use humhub\modules\aiops\Module;
use Yii;

/**
 * Deteccao deterministica.
 *
 * Tudo que resulta em contencao nasce aqui, em regra explicita e auditavel —
 * nunca de um julgamento do modelo. O modelo entra so depois, e apenas para
 * desempatar casos ambiguos ou escrever prosa. Se o provedor sumir, esta classe
 * continua funcionando inteira.
 */
final class RulesEngine
{
    public function __construct(private Module $module)
    {
    }

    /**
     * Sinais de spam num texto. Devolve lista de motivos e um score 0..1.
     *
     * Score alto nao pune sozinho: o chamador ainda checa allowlist, se a conta
     * e automatizada e se a capacidade esta ligada.
     *
     * @return array{score:float,reasons:string[]}
     */
    public function spamSignals(string $text): array
    {
        $reasons = [];
        $score = 0.0;
        $normalized = mb_strtolower(trim($text));
        $length = mb_strlen($normalized);

        $linkCount = preg_match_all('#https?://#i', $text);
        if ($linkCount >= 3) {
            $reasons[] = "muitos links ({$linkCount})";
            $score += 0.3;
        }

        if ($length > 20) {
            $upper = preg_match_all('/[A-ZÀ-Ý]/u', $text);
            $ratio = $upper / max(1, mb_strlen($text));
            if ($ratio > 0.6) {
                $reasons[] = 'predominancia de maiusculas';
                $score += 0.2;
            }
        }

        if (preg_match('/(.)\1{9,}/u', $text) === 1) {
            $reasons[] = 'caractere repetido em excesso';
            $score += 0.2;
        }

        foreach ($this->module->getDenylist() as $term) {
            if ($term !== '' && str_contains($normalized, mb_strtolower($term))) {
                $reasons[] = 'termo em lista de bloqueio';
                $score += 0.4;
                break;
            }
        }

        // Tentativa de injecao e sinal de risco, mas nao e spam por si so:
        // numa rede que discute IA, falar sobre injecao de prompt e legitimo.
        // Por isso pontua pouco e serve sobretudo para marcar revisao humana.
        if (Sanitizer::looksLikeInjection($text)) {
            $reasons[] = 'padrao de injecao de instrucao';
            $score += 0.15;
        }

        return ['score' => min($score, 1.0), 'reasons' => $reasons];
    }

    /**
     * Conteudo duplicado publicado pelo mesmo autor numa janela curta —
     * o padrao mais confiavel de conta automatizada descontrolada.
     *
     * @return array{count:int,window_minutes:int}
     */
    public function duplicateSignals(int $userId, string $text, int $windowMinutes = 60): array
    {
        $hash = md5(mb_strtolower(trim($text)));
        $since = gmdate('Y-m-d H:i:s', time() - ($windowMinutes * 60));

        $rows = Yii::$app->db->createCommand(
            'SELECT p.message FROM post p
             JOIN content c ON c.object_id = p.id AND c.object_model = :model
             WHERE c.created_by = :uid AND c.created_at >= :since',
            [':model' => 'humhub\\modules\\post\\models\\Post', ':uid' => $userId, ':since' => $since]
        )->queryColumn();

        $count = 0;
        foreach ($rows as $row) {
            if (md5(mb_strtolower(trim((string)$row))) === $hash) {
                $count++;
            }
        }

        return ['count' => $count, 'window_minutes' => $windowMinutes];
    }

    /**
     * Volume de publicacao numa janela — base do limite de taxa.
     */
    public function postVolume(int $userId, int $windowMinutes = 60): int
    {
        $since = gmdate('Y-m-d H:i:s', time() - ($windowMinutes * 60));

        return (int)Yii::$app->db->createCommand(
            'SELECT COUNT(*) FROM content WHERE created_by = :uid AND created_at >= :since',
            [':uid' => $userId, ':since' => $since]
        )->queryScalar();
    }

    /**
     * Denuncias abertas por conteudo, para triagem e escalonamento.
     *
     * @return array<int,array{content_id:int,reports:int,reasons:string}>
     */
    public function openReports(int $limit = 50): array
    {
        if (!$this->tableExists('report_content')) {
            return [];
        }

        return Yii::$app->db->createCommand(
            'SELECT content_id, COUNT(*) AS reports, GROUP_CONCAT(DISTINCT reason) AS reasons
             FROM report_content
             GROUP BY content_id
             ORDER BY reports DESC
             LIMIT :limit',
            [':limit' => $limit]
        )->queryAll();
    }

    /** Uma denuncia passou do limiar que exige olho humano? */
    public function isEscalated(int $reportCount): bool
    {
        return $reportCount >= $this->module->getEscalationThreshold();
    }

    /** Conta protegida de qualquer contencao autonoma. */
    public function isAllowlisted(string $username): bool
    {
        foreach ($this->module->getAllowlist() as $entry) {
            if (strcasecmp($entry, $username) === 0) {
                return true;
            }
        }

        return false;
    }

    private function tableExists(string $table): bool
    {
        return Yii::$app->db->getTableSchema($table, true) !== null;
    }
}
