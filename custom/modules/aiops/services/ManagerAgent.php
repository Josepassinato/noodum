<?php

namespace humhub\modules\aiops\services;

use humhub\modules\aiops\Module;
use humhub\modules\aiops\models\Conversation;
use humhub\modules\aiops\models\Direction;
use humhub\modules\aiops\models\Proposal;
use humhub\modules\aiops\services\llm\LlmAdapter;
use humhub\modules\aiops\services\llm\OpenAiCompatibleAdapter;
use Throwable;
use Yii;

/**
 * O gerente da plataforma no canal do dono.
 *
 * Conversa em linguagem natural sobre o que esta acontecendo no NOODUM,
 * responde perguntas com os numeros reais da plataforma e registra as direcoes
 * e ideias que o dono da.
 *
 * Tres limites deliberados:
 *
 *  1. Um provedor, nao o conselho. O conselho existe para decisao com
 *     consequencia — tres votos, quorum de dois. Conversa nao tem consequencia,
 *     e pagar tres inferencias por mensagem so adiciona custo e latencia num
 *     webhook que precisa responder rapido.
 *  2. So fatos fornecidos. Os numeros entram prontos no prompt; o gerente nao
 *     consulta banco por conta propria e nao pode inventar metrica ausente.
 *  3. Registrar direcao nao e executar direcao. A direcao vira insumo do
 *     briefing do conselho. O que muda a plataforma continua nascendo como
 *     proposta e exigindo aprovacao no painel autenticado.
 */
final class ManagerAgent
{
    /** Ordem de preferencia: o mais barato que estiver configurado ganha. */
    private const PROVIDERS = [
        ['prefix' => 'AIOPS_GEMINI', 'base' => 'https://generativelanguage.googleapis.com/v1beta/openai', 'name' => 'gemini'],
        ['prefix' => 'AIOPS_XAI', 'base' => 'https://api.x.ai/v1', 'name' => 'xai'],
        ['prefix' => 'AIOPS_OPENAI', 'base' => 'https://api.openai.com/v1', 'name' => 'openai'],
    ];

    private LlmAdapter $llm;

    public function __construct(private Module $module, ?LlmAdapter $llm = null)
    {
        $this->llm = $llm ?? self::resolveProvider();
    }

    /**
     * Um unico provedor, escolhido por custo. Sem chave nenhuma, devolve um
     * adaptador indisponivel e o canal cai no menu de comandos.
     */
    public static function resolveProvider(): LlmAdapter
    {
        foreach (self::PROVIDERS as $provider) {
            $apiKey = trim((string)getenv($provider['prefix'] . '_API_KEY'));
            $model = trim((string)getenv($provider['prefix'] . '_MODEL'));
            if ($apiKey === '' || $model === '') {
                continue;
            }

            $baseUrl = trim((string)getenv($provider['prefix'] . '_BASE_URL')) ?: $provider['base'];

            // Teto folgado de proposito: modelo com raciocinio interno gasta
            // parte do orcamento pensando antes de escrever a resposta.
            return new OpenAiCompatibleAdapter($apiKey, $baseUrl, $model, 25, $provider['name'], 3000);
        }

        return new llm\NullAdapter();
    }

    public function isAvailable(): bool
    {
        return $this->llm->isAvailable();
    }

    /**
     * Responde uma mensagem do dono.
     *
     * Devolve null quando nao ha provedor ou a inferencia falhou — quem chama
     * decide o fallback, porque o canal precisa responder alguma coisa sempre.
     */
    public function reply(string $chatId, string $message): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            $answer = $this->llm->summarize(
                $this->buildUserContent($chatId, $message),
                $this->buildPersona()
            );
        } catch (Throwable $e) {
            Yii::warning('aiops: gerente falhou na inferencia: ' . $e->getMessage(), 'aiops');

            return null;
        }

        if ($answer === null || trim($answer) === '') {
            return null;
        }

        $parsed = $this->parse($answer);
        $reply = $parsed['reply'];

        if ($parsed['direction'] !== null) {
            $recorded = Direction::record($chatId, $parsed['direction']);
            if ($recorded !== null) {
                $reply .= "\n\n--\nDirecao registrada (#{$recorded->id}). "
                    . 'Ela entra no briefing do conselho; nada e executado sem sua aprovacao no painel.';
            }
        }

        Conversation::remember($chatId, Conversation::ROLE_OWNER, $message);
        Conversation::remember($chatId, Conversation::ROLE_MANAGER, $reply);
        Conversation::prune($chatId);

        return $reply;
    }

    private function buildPersona(): string
    {
        return implode("\n", [
            'Voce e o gerente da plataforma NOODUM, uma rede social open-source onde humanos e agentes de IA convivem.',
            'Voce conversa com o dono da plataforma pelo canal privado dele no Telegram.',
            '',
            'Como voce fala:',
            '- Portugues do Brasil, direto, sem enrolacao e sem bajulacao.',
            '- Curto: e Telegram, nao relatorio. No maximo uns 10 linhas, salvo se pedirem detalhe.',
            '- Sem markdown pesado. Pode usar hifen para lista.',
            '- Sem emoji: a conexao do banco nao guarda caractere de 4 bytes e o historico fica sujo.',
            '',
            'O que voce faz:',
            '- Responde o que esta acontecendo na plataforma usando SOMENTE os fatos fornecidos abaixo.',
            '- Se um numero nao estiver nos fatos, diga que nao tem esse dado. NUNCA invente metrica.',
            '- Quando o dono der uma direcao, ideia ou prioridade, acolha e confirme o registro.',
            '- Quando fizer sentido, aponte o que voce faria a seguir — mas como sugestao, nao como feito.',
            '',
            'Limites que voce nao ultrapassa:',
            '- Voce NAO executa acao na plataforma. Nao modera, nao publica, nao remove, nao muda configuracao.',
            '- Toda mudanca real nasce como proposta e exige aprovacao humana no painel autenticado.',
            '- Se pedirem para executar algo, explique que registra a direcao e que a execucao passa pelo painel.',
            '- Nunca peca nem repita credencial, chave ou token.',
            '',
            'Formato da sua resposta — responda SOMENTE com JSON valido, sem cercas de codigo:',
            '{"reply":"<sua resposta ao dono>","direction":<null ou "<a direcao dele em uma frase>">}',
            'Preencha "direction" apenas quando a mensagem contiver de fato uma direcao, ideia ou prioridade nova.',
            'Pergunta pura tem "direction": null.',
        ]);
    }

    private function buildUserContent(string $chatId, string $message): string
    {
        $blocks = [
            "FATOS DA PLATAFORMA AGORA:\n" . $this->factsBlock(),
        ];

        $directions = $this->directionsBlock();
        if ($directions !== '') {
            $blocks[] = "DIRECOES QUE O DONO JA DEU:\n" . $directions;
        }

        $history = $this->historyBlock($chatId);
        if ($history !== '') {
            $blocks[] = "CONVERSA ATE AQUI:\n" . $history;
        }

        $blocks[] = "MENSAGEM DO DONO AGORA:\n" . $message;

        return implode("\n\n", $blocks);
    }

    private function factsBlock(): string
    {
        try {
            $health = (new OperationsManager($this->module))->healthSnapshot();
        } catch (Throwable) {
            $health = [];
        }

        $lines = [
            '- operacao de IA: ' . ($this->module->isEnabled() ? 'ligada' : 'pausada'),
            '- usuarios ativos: ' . ($health['users_total'] ?? 'desconhecido'),
            '- publicacoes nas ultimas 24h: ' . ($health['content_24h'] ?? 'desconhecido'),
            '- comunidades: ' . ($health['spaces_total'] ?? 'desconhecido'),
            '- denuncias abertas: ' . ($health['reports_open'] ?? 'desconhecido'),
            '- contencoes ativas: ' . ($health['enforcements_active'] ?? 'desconhecido'),
            '- eventos de auditoria nas 24h: ' . ($health['audit_24h'] ?? 'desconhecido'),
            '- conselho configurado: ' . ($health['council_configured'] ?? 0) . '/3',
        ];

        try {
            $pending = Proposal::findPending()->limit(5)->all();
        } catch (Throwable) {
            $pending = [];
        }

        if ($pending === []) {
            $lines[] = '- propostas aguardando decisao humana: nenhuma';
        } else {
            $lines[] = '- propostas aguardando decisao humana: ' . count($pending);
            foreach ($pending as $proposal) {
                $lines[] = sprintf(
                    '  - #%d %s — %s (vence %s UTC)',
                    $proposal->id,
                    $proposal->capability,
                    $proposal->proposed_action,
                    $proposal->expires_at
                );
            }
        }

        return implode("\n", $lines);
    }

    private function directionsBlock(): string
    {
        try {
            $directions = Direction::activeForBriefing(8);
        } catch (Throwable) {
            return '';
        }

        $lines = [];
        foreach (array_reverse($directions) as $direction) {
            $lines[] = '- (' . $direction->created_at . ' UTC) ' . $direction->content;
        }

        return implode("\n", $lines);
    }

    private function historyBlock(string $chatId): string
    {
        try {
            $turns = Conversation::recentFor($chatId);
        } catch (Throwable) {
            return '';
        }

        $lines = [];
        foreach ($turns as $turn) {
            $who = $turn->role === Conversation::ROLE_OWNER ? 'DONO' : 'GERENTE';
            $lines[] = $who . ': ' . mb_substr($turn->content, 0, 500);
        }

        return implode("\n", $lines);
    }

    /**
     * O modelo foi instruido a devolver JSON, mas modelo erra formato. Texto
     * solto continua sendo uma resposta util, entao ele vira a resposta.
     *
     * @return array{reply:string,direction:?string}
     */
    private function parse(string $answer): array
    {
        $clean = trim($answer);
        // Remove cerca de codigo quando o modelo insiste em usar uma.
        $clean = (string)preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $clean);
        $clean = trim($clean);

        $decoded = json_decode($clean, true);
        if (!is_array($decoded) || !isset($decoded['reply'])) {
            return ['reply' => $clean, 'direction' => null];
        }

        $reply = trim((string)$decoded['reply']);
        if ($reply === '') {
            return ['reply' => $clean, 'direction' => null];
        }

        $direction = $decoded['direction'] ?? null;
        $direction = is_string($direction) && trim($direction) !== '' ? trim($direction) : null;

        return ['reply' => $reply, 'direction' => $direction];
    }
}
