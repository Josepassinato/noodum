<?php

namespace humhub\modules\aiops\services;

use humhub\modules\aiops\models\AuditEntry;
use humhub\modules\aiops\models\Enforcement;
use humhub\modules\aiops\models\Proposal;
use humhub\modules\aiops\Module;

/**
 * Resumo operacional diario.
 *
 * Os numeros vem sempre do banco, nunca do modelo. O modelo, quando disponivel,
 * apenas escreve um paragrafo de leitura sobre esses numeros ja apurados — se
 * ele estiver fora do ar, o digest sai completo, so sem a prosa.
 */
final class Digest
{
    public function __construct(private Module $module, private OperationsManager $manager)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function build(): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - 86400);

        $facts = [
            'period' => 'ultimas 24h',
            'generated_at' => gmdate('Y-m-d H:i:s'),
            'health' => $this->manager->healthSnapshot(),
            'autonomous_actions' => AuditEntry::find()
                ->where(['>=', 'created_at', $since])
                ->andWhere(['result' => AuditEntry::RESULT_EXECUTED])->count(),
            'observations' => AuditEntry::find()
                ->where(['>=', 'created_at', $since])
                ->andWhere(['result' => AuditEntry::RESULT_OBSERVED])->count(),
            'blocked_attempts' => AuditEntry::find()
                ->where(['>=', 'created_at', $since])
                ->andWhere(['result' => AuditEntry::RESULT_BLOCKED])->count(),
            'reversals' => AuditEntry::find()
                ->where(['>=', 'created_at', $since])
                ->andWhere(['result' => AuditEntry::RESULT_REVERTED])->count(),
            'proposals_pending' => Proposal::findPending()->count(),
            'proposals_created' => Proposal::find()->where(['>=', 'created_at', $since])->count(),
            'enforcements_active' => Enforcement::findActive()->count(),
            'agent_compliance' => (new AgentCompliance())->summary(),
        ];

        $facts['narrative'] = $this->narrate($facts);

        return $facts;
    }

    /**
     * Paragrafo de leitura. Recebe apenas numeros ja apurados — nenhum texto de
     * usuario chega ate aqui, entao esta chamada nao tem superficie de injecao.
     */
    private function narrate(array $facts): ?string
    {
        if (!$this->module->isCapabilityEnabled('daily_digest')) {
            return null;
        }

        $llm = $this->manager->getLlm();
        if (!$llm->isAvailable()) {
            return null;
        }

        $payload = json_encode([
            'saude' => $facts['health'],
            'acoes_autonomas' => $facts['autonomous_actions'],
            'observacoes' => $facts['observations'],
            'tentativas_bloqueadas' => $facts['blocked_attempts'],
            'reversoes' => $facts['reversals'],
            'propostas_pendentes' => $facts['proposals_pending'],
            'contencoes_ativas' => $facts['enforcements_active'],
            'conformidade_agentes' => $facts['agent_compliance'],
        ], JSON_UNESCAPED_UNICODE);

        return $llm->summarize(
            $payload,
            'Voce escreve um resumo operacional curto (maximo 5 frases) para o administrador '
            . 'de uma rede social de humanos e agentes de IA. Use apenas os numeros fornecidos. '
            . 'Nao invente dados. Nao sugira acoes irreversiveis. Escreva em portugues do Brasil.'
        );
    }

    /** Versao em texto, para log e leitura no terminal. */
    public function render(array $digest): string
    {
        $h = $digest['health'];
        $c = $digest['agent_compliance'];

        $lines = [
            '=== Digest operacional — ' . $digest['generated_at'] . ' UTC (' . $digest['period'] . ') ===',
            sprintf('Rede:        %d usuarios | %d publicacoes/24h | %d comunidades', $h['users_total'], $h['content_24h'], $h['spaces_total']),
            sprintf('Moderacao:   %d denuncias abertas | %d contencoes ativas', $h['reports_open'], $digest['enforcements_active']),
            sprintf('IA:          %d acoes autonomas | %d observacoes | %d reversoes', $digest['autonomous_actions'], $digest['observations'], $digest['reversals']),
            sprintf('Governanca:  %d propostas pendentes | %d criadas | %d tentativas bloqueadas', $digest['proposals_pending'], $digest['proposals_created'], $digest['blocked_attempts']),
            sprintf('Conformidade: %d perfis com pendencia (%d em carencia, %d acionaveis)', $c['total_issues'], $c['in_grace'], $c['actionable']),
            'Modelo:      ' . $h['llm'],
        ];

        if (!empty($digest['narrative'])) {
            $lines[] = '';
            $lines[] = $digest['narrative'];
        }

        return implode("\n", $lines);
    }
}
