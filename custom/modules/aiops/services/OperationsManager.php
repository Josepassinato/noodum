<?php

namespace humhub\modules\aiops\services;

use humhub\modules\aiops\components\Sanitizer;
use humhub\modules\aiops\models\AuditEntry;
use humhub\modules\aiops\models\Enforcement;
use humhub\modules\aiops\services\llm\LlmAdapter;
use humhub\modules\aiops\services\llm\NullAdapter;
use humhub\modules\aiops\services\llm\OpenAiCompatibleAdapter;
use humhub\modules\aiops\Module;
use humhub\modules\user\models\User;
use Throwable;
use Yii;

/**
 * O gerente de operacoes: um ciclo de observacao sobre a rede.
 *
 * Ele nao decide nada sozinho — apenas junta sinais deterministicos
 * (RulesEngine), pede desempate ao modelo quando ha ambiguidade, e entrega ao
 * Executor, que aplica a governanca.
 *
 * Robustez: cada etapa roda dentro de try/catch proprio. Uma etapa que quebra
 * nao derruba as outras e, principalmente, nao derruba a rede social. Se este
 * worker sumir por completo, o HumHub continua funcionando normalmente e
 * nenhuma permissao de moderacao afrouxa — as contencoes existentes apenas
 * expiram sozinhas.
 */
final class OperationsManager
{
    private RulesEngine $rules;
    private Executor $executor;
    private AgentCompliance $compliance;
    private LlmAdapter $llm;

    public function __construct(private Module $module, ?LlmAdapter $llm = null)
    {
        $this->rules = new RulesEngine($module);
        $this->executor = new Executor($module);
        $this->compliance = new AgentCompliance();
        $this->llm = $llm ?? self::resolveAdapter();
    }

    /**
     * Escolhe o provedor a partir do ambiente. Sem chave, cai no NullAdapter e
     * a camada roda 100% deterministica.
     */
    public static function resolveAdapter(): LlmAdapter
    {
        $provider = strtolower((string)getenv('AIOPS_LLM_PROVIDER'));
        if ($provider === '' || $provider === 'none' || $provider === 'null') {
            return new NullAdapter();
        }

        $adapter = new OpenAiCompatibleAdapter();

        return $adapter->isAvailable() ? $adapter : new NullAdapter();
    }

    public function getLlm(): LlmAdapter
    {
        return $this->llm;
    }

    /**
     * Um ciclo completo de monitoramento.
     *
     * @return array<string,mixed> contadores por etapa, para log e painel
     */
    public function runCycle(string $trigger = 'cron'): array
    {
        // Interruptor geral: primeira coisa checada, sempre.
        if (!$this->module->isEnabled()) {
            return ['skipped' => 'camada de IA desligada (kill switch)'];
        }

        $result = ['llm' => $this->llm->describe()];

        foreach ([
            'housekeeping' => fn() => $this->runHousekeeping(),
            'reports' => fn() => $this->triageReports($trigger),
            'spam' => fn() => $this->scanRecentPosts($trigger),
            'agent_compliance' => fn() => $this->checkAgentCompliance($trigger),
        ] as $step => $fn) {
            try {
                $result[$step] = $fn();
            } catch (Throwable $e) {
                // Falha de etapa e registrada e o ciclo continua. A alternativa
                // — abortar tudo — deixaria a rede sem observacao por causa de
                // um unico caso ruim.
                Yii::error('aiops: etapa ' . $step . ' falhou: ' . $e->getMessage(), 'aiops');
                $result[$step] = ['error' => $e->getMessage()];
            }
        }

        return $result;
    }

    /** Expira contencoes e propostas vencidas. */
    private function runHousekeeping(): array
    {
        if (!$this->module->isCapabilityEnabled('housekeeping')) {
            return ['skipped' => true];
        }

        return [
            'enforcements_expired' => $this->executor->expireStaleEnforcements(),
            'proposals_expired' => $this->executor->expireStaleProposals(),
        ];
    }

    /**
     * Triagem de denuncias: classifica, e escala para decisao humana quando
     * passa do limiar. A IA nunca remove conteudo por conta propria.
     */
    private function triageReports(string $trigger): array
    {
        if (!$this->module->isCapabilityEnabled('classify_report')) {
            return ['skipped' => true];
        }

        $reports = $this->rules->openReports();
        $escalated = 0;

        foreach ($reports as $report) {
            $contentId = (int)$report['content_id'];
            $count = (int)$report['reports'];

            $this->executor->observe('classify_report', $trigger, 'content', $contentId, [
                'reports' => $count,
                'reasons' => $report['reasons'],
                'escalated' => $this->rules->isEscalated($count),
            ]);

            if (!$this->rules->isEscalated($count)) {
                continue;
            }

            // Volume de denuncias e sinal forte e verificavel; a confianca vem
            // da contagem, nao de julgamento do modelo.
            $confidence = min(0.6 + (0.1 * ($count - $this->module->getEscalationThreshold())), 0.95);

            $proposal = $this->executor->propose(
                'remove_content',
                'content',
                $contentId,
                "Content accumulated {$count} reports (threshold: {$this->module->getEscalationThreshold()}).",
                'Review the reports and decide whether the content should be removed',
                'Removal affects the author and participants. Reversible only by manual restoration.',
                ['reports' => $count, 'reasons' => $report['reasons']],
                $trigger,
                $confidence
            );

            if ($proposal !== null) {
                $escalated++;
            }
        }

        return ['reviewed' => count($reports), 'escalated' => $escalated];
    }

    /**
     * Varre publicacoes recentes em busca de spam e automacao descontrolada.
     */
    private function scanRecentPosts(string $trigger): array
    {
        if (!$this->module->isCapabilityEnabled('flag_spam')) {
            return ['skipped' => true];
        }

        $since = gmdate('Y-m-d H:i:s', time() - 3600);
        $rows = Yii::$app->db->createCommand(
            'SELECT p.id, p.message, c.id AS content_id, c.created_by
             FROM post p JOIN content c ON c.object_id = p.id AND c.object_model = :model
             WHERE c.created_at >= :since
             ORDER BY c.id DESC LIMIT 100',
            [':model' => 'humhub\\modules\\post\\models\\Post', ':since' => $since]
        )->queryAll();

        $flagged = 0;
        $contained = 0;

        foreach ($rows as $row) {
            $message = (string)$row['message'];
            $userId = (int)$row['created_by'];
            $signals = $this->rules->spamSignals($message);

            if ($signals['reasons'] === []) {
                continue;
            }

            $duplicates = $this->rules->duplicateSignals($userId, $message);
            $volume = $this->rules->postVolume($userId);
            $score = $signals['score'];

            if ($duplicates['count'] >= 3) {
                $score = min($score + 0.3, 1.0);
                $signals['reasons'][] = "content repeated {$duplicates['count']} times in 1h";
            }
            if ($volume >= 30) {
                $score = min($score + 0.2, 1.0);
                $signals['reasons'][] = "high volume ({$volume} publications in 1h)";
            }

            $evidence = [
                'content_id' => (int)$row['content_id'],
                'signals' => $signals['reasons'],
                'score' => $score,
                'duplicates' => $duplicates['count'],
                'volume_1h' => $volume,
                'injection_pattern' => Sanitizer::looksLikeInjection($message),
            ];

            $this->executor->observe('flag_spam', $trigger, 'content', (int)$row['content_id'], $evidence, $score);
            $flagged++;

            // Contencao autonoma so alcanca conta automatizada. Conta humana
            // com sinal de spam vira proposta para um humano decidir — conter
            // pessoa sem revisao seria passar do que a autonomia comporta.
            $user = User::findOne($userId);
            if ($user === null) {
                continue;
            }

            $isAgent = $this->isAgentAccount($userId);

            if ($isAgent && $score >= 0.8) {
                $enforcement = $this->executor->enforce(
                    'rate_limit_agent',
                    Enforcement::SUBJECT_USER,
                    $userId,
                    'Automated account with strong abuse signals: ' . implode('; ', $signals['reasons']),
                    $evidence,
                    $trigger,
                    $score,
                    $user->username
                );
                if ($enforcement !== null) {
                    $contained++;
                }
            } elseif ($score >= 0.8) {
                $this->executor->propose(
                    'suspend_account',
                    'user',
                    $userId,
                    'Human account with strong abuse signals: ' . implode('; ', $signals['reasons']),
                    'Review the account and decide whether to suspend it',
                    'Suspension prevents access and can be reversed by an administrator.',
                    $evidence,
                    $trigger,
                    $score
                );
            }
        }

        return ['scanned' => count($rows), 'flagged' => $flagged, 'contained' => $contained];
    }

    /**
     * Conformidade de identidade: sinaliza sempre; contem so depois da
     * carencia, e ainda assim de forma temporaria e reversivel.
     */
    private function checkAgentCompliance(string $trigger): array
    {
        if (!$this->module->isCapabilityEnabled('flag_agent_compliance')) {
            return ['skipped' => true];
        }

        $findings = $this->compliance->scan();
        $quarantined = 0;

        foreach ($findings as $finding) {
            $this->executor->observe(
                'flag_agent_compliance',
                $trigger,
                'user',
                $finding['user_id'],
                [
                    'username' => $finding['username'],
                    'profile_type' => $finding['profile_type'],
                    'issues' => $finding['issues'],
                    'in_grace' => $finding['in_grace'],
                ],
                1.0 // checagem de campo vazio e factual, nao estimativa
            );

            if ($finding['in_grace']) {
                continue;
            }

            $enforcement = $this->executor->enforce(
                'quarantine_agent',
                Enforcement::SUBJECT_USER,
                $finding['user_id'],
                'Non-compliant profile after the grace period: ' . implode('; ', $finding['issues']),
                ['issues' => $finding['issues'], 'username' => $finding['username']],
                $trigger,
                1.0,
                $finding['username']
            );

            if ($enforcement !== null) {
                $quarantined++;
            }
        }

        return ['findings' => count($findings), 'quarantined' => $quarantined];
    }

    /** A conta e operada por IA? Decide pelo tipo de perfil declarado. */
    private function isAgentAccount(int $userId): bool
    {
        $schema = Yii::$app->db->getTableSchema('profile', true);
        if ($schema === null || !isset($schema->columns['profile_type'])) {
            return false;
        }

        $type = Yii::$app->db->createCommand(
            'SELECT profile_type FROM profile WHERE user_id = :uid',
            [':uid' => $userId]
        )->queryScalar();

        return $type === 'agent';
    }

    /** Sinais de saude da plataforma, para o painel e o digest. */
    public function healthSnapshot(): array
    {
        $count = static function (string $sql, array $params = []): int {
            try {
                return (int)Yii::$app->db->createCommand($sql, $params)->queryScalar();
            } catch (Throwable) {
                return 0;
            }
        };

        $dayAgo = gmdate('Y-m-d H:i:s', time() - 86400);

        return [
            'users_total' => $count('SELECT COUNT(*) FROM user WHERE status = 1'),
            'content_24h' => $count('SELECT COUNT(*) FROM content WHERE created_at >= :s', [':s' => $dayAgo]),
            'spaces_total' => $count('SELECT COUNT(*) FROM space'),
            'reports_open' => $count('SELECT COUNT(*) FROM report_content'),
            'enforcements_active' => Enforcement::findActive()->count(),
            'audit_24h' => AuditEntry::find()->where(['>=', 'created_at', $dayAgo])->count(),
            'llm' => $this->llm->describe(),
            'llm_available' => $this->llm->isAvailable(),
        ];
    }
}
