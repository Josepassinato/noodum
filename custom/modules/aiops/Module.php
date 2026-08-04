<?php

namespace humhub\modules\aiops;

use humhub\modules\aiops\components\Governance;
use yii\helpers\Url;

/**
 * Modulo de operacao assistida por IA.
 *
 * Duas chaves controlam tudo:
 *  - o interruptor geral (`enabled`), que desliga a camada inteira;
 *  - um interruptor por capacidade, que so vale se o geral estiver ligado.
 *
 * Ambos partem de posicao conservadora: com o modulo recem-instalado, a IA
 * observa, classifica e propoe, mas nao aplica nenhuma contencao. Ligar
 * contencao autonoma e uma decisao explicita de quem opera.
 */
class Module extends \humhub\components\Module
{
    /**
     * Capacidades ligadas por padrao.
     *
     * Nenhuma delas altera o estado da rede: sao leitura, classificacao,
     * sinalizacao e escrita na fila de propostas. As que efetivamente contem
     * uma conta (`rate_limit_agent`, `quarantine_agent`) ficam desligadas ate
     * alguem ligar de propria vontade.
     */
    private const DEFAULT_ON = [
        'summarize_activity',
        'classify_report',
        'flag_spam',
        'suggest_tags',
        'daily_digest',
        'housekeeping',
        'flag_agent_compliance',
        'answer_faq',
    ];

    public function getConfigUrl()
    {
        return Url::to(['/aiops/settings']);
    }

    /** Interruptor geral. Desligado = a camada inteira para. */
    public function isEnabled(): bool
    {
        return (bool)$this->settings->get('enabled', 0);
    }

    public function setEnabled(bool $value): void
    {
        $this->settings->set('enabled', $value ? 1 : 0);
    }

    /**
     * Uma capacidade so esta ativa se o interruptor geral estiver ligado E o
     * interruptor dela estiver ligado E ela nao for de nivel 3.
     *
     * A checagem de nivel 3 aqui e redundante — o executor tambem recusa — mas
     * redundancia em barreira de seguranca e proposital.
     */
    public function isCapabilityEnabled(string $capability): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        if (Governance::isHumanOnly($capability)) {
            return false;
        }

        $default = in_array($capability, self::DEFAULT_ON, true) ? 1 : 0;

        return (bool)$this->settings->get('cap.' . $capability, $default);
    }

    public function setCapabilityEnabled(string $capability, bool $value): void
    {
        if (Governance::isHumanOnly($capability)) {
            return; // nivel 3 nao e configuravel — nao existe interruptor para ligar
        }
        $this->settings->set('cap.' . $capability, $value ? 1 : 0);
    }

    /** Duracao de uma contencao autonoma, limitada pelo teto de governanca. */
    public function getEnforcementSeconds(): int
    {
        $value = (int)$this->settings->get('enforcement_seconds', 3600);

        return max(300, min($value, Governance::MAX_ENFORCEMENT_SECONDS));
    }

    /** Confianca minima para a IA propor algo. Abaixo disso, so observa. */
    public function getMinConfidence(): float
    {
        $value = (float)$this->settings->get('min_confidence', 0.75);

        return max(0.0, min($value, 1.0));
    }

    /** Denuncias acumuladas no mesmo conteudo que disparam escalonamento. */
    public function getEscalationThreshold(): int
    {
        return max(1, (int)$this->settings->get('escalation_threshold', 3));
    }

    /** Usuarios nunca alcancados por contencao autonoma (um por linha). */
    public function getAllowlist(): array
    {
        return $this->parseList((string)$this->settings->get('allowlist', ''));
    }

    /** Termos que marcam conteudo como suspeito de forma deterministica. */
    public function getDenylist(): array
    {
        return $this->parseList((string)$this->settings->get('denylist', ''));
    }

    private function parseList(string $raw): array
    {
        $items = preg_split('/[\r\n,]+/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $items), static fn($v) => $v !== ''));
    }

    /** Conta da plataforma sob a qual a IA age, sempre identificavel. */
    public function getOperatorUsername(): string
    {
        return (string)$this->settings->get('operator_username', 'platform_ops_ai');
    }
}
