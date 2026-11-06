<?php

namespace humhub\modules\aiops\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\aiops\models\Proposal;
use humhub\modules\aiops\services\Digest;
use humhub\modules\aiops\services\ManagerAgent;
use humhub\modules\aiops\services\OperationsManager;
use humhub\modules\aiops\services\TelegramGateway;
use Throwable;
use Yii;
use yii\web\Response;

/** Public webhook authenticated by Telegram's secret header and owner allowlist. */
final class TelegramController extends Controller
{
    public $enableCsrfValidation = false;
    public $access = ControllerAccess::class;
    protected $doNotInterceptActionIds = ['*'];

    public function beforeAction($action)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return parent::beforeAction($action);
    }

    public function actionWebhook(): array
    {
        $telegram = TelegramGateway::fromEnvironment();
        $secret = (string)Yii::$app->request->headers->get('X-Telegram-Bot-Api-Secret-Token', '');

        if (!$telegram->isReady() || !$telegram->acceptsSecret($secret)) {
            Yii::$app->response->statusCode = 404;
            return ['ok' => false];
        }

        $raw = (string)Yii::$app->request->getRawBody();
        if ($raw === '' || strlen($raw) > 65536) {
            return ['ok' => true];
        }

        $update = json_decode($raw, true);
        if (!is_array($update)) {
            return ['ok' => true];
        }

        $updateId = (int)($update['update_id'] ?? 0);
        if ($updateId > 0 && !Yii::$app->cache->add('aiops.telegram.update.' . $updateId, 1, 86400)) {
            return ['ok' => true];
        }

        $message = is_array($update['message'] ?? null) ? $update['message'] : [];
        $chatId = (string)($message['chat']['id'] ?? '');
        $chatType = (string)($message['chat']['type'] ?? '');
        $text = trim((string)($message['text'] ?? ''));

        if (!$telegram->isAllowedChat($chatId, $chatType) || $text === '') {
            return ['ok' => true];
        }

        $command = strtolower((string)strtok($text, " \n\t"));
        $command = preg_replace('/@[^\s]+$/', '', $command) ?: $command;
        $telegram->sendMessage($chatId, $this->replyFor($command, $text, $chatId));

        return ['ok' => true];
    }

    /**
     * Frases em linguagem natural (PT/EN) mapeadas para os comandos de leitura.
     * Casamento por palavra-chave, sem LLM: a resposta precisa caber no timeout
     * do webhook do Telegram e o canal nao justifica custo de inferencia.
     */
    private const INTENT_KEYWORDS = [
        '/proposals' => [
            'proposta', 'propostas', 'proposal', 'proposals', 'aprovar', 'aprovacao',
            'aprovacoes', 'pendente', 'pendentes', 'pending', 'decisao', 'decisoes',
            'decidir', 'approve', 'governanca',
        ],
        '/council' => [
            'conselho', 'council', 'provedor', 'provedores', 'provider', 'providers',
            'llm', 'quorum', 'modelo', 'modelos', 'openai', 'grok', 'gemini',
        ],
        '/digest' => [
            'digest', 'resumo', 'resumao', 'sumario', 'relatorio', 'report',
            '24h', '24 h', 'ultimas 24', 'last 24', 'ontem',
        ],
        '/status' => [
            'status', 'saude', 'health', 'plataforma', 'platform', 'usuario',
            'usuarios', 'users', 'metrica', 'metricas', 'numeros', 'como esta',
            'como anda', 'funcionando', 'no ar', 'esta rodando',
        ],
        '/help' => [
            'ajuda', 'help', 'comando', 'comandos', 'command', 'commands', 'menu',
            'opcoes', 'opcao', 'o que voce faz', 'o que vc faz', 'o que pode fazer',
            'quais', 'como usar',
        ],
    ];

    private const GREETINGS = [
        'oi', 'ola', 'e ai', 'eai', 'opa', 'hello', 'hi', 'hey', 'bom dia',
        'boa tarde', 'boa noite', 'tudo bem', 'tudo bom', 'beleza',
    ];

    private function replyFor(string $command, string $text = '', string $chatId = ''): string
    {
        return match ($command) {
            '/start', '/help' => $this->helpReply(),
            '/status' => $this->statusReply(),
            '/council' => $this->councilReply(),
            '/proposals' => $this->proposalsReply(),
            '/digest' => $this->digestReply(),
            default => $this->conversationalReply($text, $chatId),
        };
    }

    /**
     * Frase livre vai para o gerente da plataforma.
     *
     * Comando com barra continua deterministico e sem custo — so o que nao e
     * comando paga inferencia. Se o gerente estiver indisponivel ou falhar, o
     * canal ainda responde: cai no casamento por palavra-chave.
     */
    private function conversationalReply(string $text, string $chatId): string
    {
        $module = Yii::$app->getModule('aiops');
        if ($chatId !== '' && $module !== null) {
            try {
                $reply = (new ManagerAgent($module))->reply($chatId, $text);
                if ($reply !== null) {
                    return $reply;
                }
            } catch (Throwable $e) {
                Yii::warning('aiops: gerente indisponivel, usando fallback: ' . $e->getMessage(), 'aiops');
            }
        }

        return $this->naturalLanguageReply($text);
    }

    /** Fallback deterministico: entender a frase que o dono escreveu sem LLM. */
    private function naturalLanguageReply(string $text): string
    {
        $normalized = $this->normalize($text);
        if ($normalized === '') {
            return $this->helpReply();
        }

        foreach (self::INTENT_KEYWORDS as $command => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $this->replyFor($command);
                }
            }
        }

        foreach (self::GREETINGS as $greeting) {
            if ($normalized === $greeting || str_starts_with($normalized, $greeting . ' ')) {
                return "Ola! Aqui e o canal do dono do NOODUM.\n\n" . $this->helpReply();
            }
        }

        return "Nao entendi esse pedido — este canal responde apenas leitura.\n\n" . $this->helpReply();
    }

    /** Minusculas, sem acento e sem pontuacao, para casar frase escrita a mao. */
    private function normalize(string $text): string
    {
        $lower = mb_strtolower(trim($text), 'UTF-8');
        $ascii = strtr($lower, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'î' => 'i', 'ì' => 'i',
            'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ò' => 'o', 'ö' => 'o',
            'ú' => 'u', 'û' => 'u', 'ù' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        $clean = preg_replace('/[^a-z0-9\s]+/', ' ', $ascii) ?? $ascii;

        return trim((string)preg_replace('/\s+/', ' ', $clean));
    }

    private function helpReply(): string
    {
        return implode("\n", [
            'NOODUM owner channel',
            '',
            '/status — platform health',
            '/council — provider council availability',
            '/proposals — pending human decisions',
            '/digest — operational summary for the last 24 hours',
            '',
            'Voce tambem pode escrever em portugues (ex.: "como esta a plataforma?",',
            '"tem proposta pendente?", "me da o resumo das ultimas 24h").',
            '',
            'This channel is read-only. Decisions and sensitive actions remain in the authenticated admin panel.',
        ]);
    }

    private function statusReply(): string
    {
        $module = Yii::$app->getModule('aiops');
        $health = (new OperationsManager($module))->healthSnapshot();

        return implode("\n", [
            'NOODUM operational status',
            'AI operations: ' . ($module->isEnabled() ? 'enabled' : 'paused'),
            'Active users: ' . $health['users_total'],
            'Publications (24h): ' . $health['content_24h'],
            'Communities: ' . $health['spaces_total'],
            'Open reports: ' . $health['reports_open'],
            'Active temporary enforcements: ' . $health['enforcements_active'],
            'Pending proposals: ' . Proposal::findPending()->count(),
        ]);
    }

    private function councilReply(): string
    {
        $module = Yii::$app->getModule('aiops');
        $health = (new OperationsManager($module))->healthSnapshot();
        $configured = (int)($health['council_configured'] ?? 0);

        return implode("\n", [
            'NOODUM provider council',
            'Configured members: ' . $configured . '/3',
            'Operational quorum: ' . (($health['llm_available'] ?? false) ? 'available (2 of 3)' : 'not available'),
            'Members: OpenAI, xAI Grok, Google Gemini',
            '',
            'No single provider can create an actionable classification.',
        ]);
    }

    private function proposalsReply(): string
    {
        $proposals = Proposal::findPending()->limit(5)->all();
        if ($proposals === []) {
            return 'There are no pending governance proposals.';
        }

        $lines = ['Pending governance proposals:'];
        foreach ($proposals as $proposal) {
            $lines[] = sprintf(
                '#%d · %s · expires %s UTC',
                $proposal->id,
                $proposal->capability,
                $proposal->expires_at
            );
        }
        $lines[] = '';
        $base = rtrim(trim((string)getenv('HUMHUB_BASE_URL')), '/');
        $lines[] = 'Review securely: ' . ($base !== '' ? $base : '') . '/aiops/approval';

        return implode("\n", $lines);
    }

    private function digestReply(): string
    {
        $module = Yii::$app->getModule('aiops');
        $manager = new OperationsManager($module);
        $digest = new Digest($module, $manager);

        return $digest->render($digest->build());
    }
}
