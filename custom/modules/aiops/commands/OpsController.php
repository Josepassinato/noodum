<?php

namespace humhub\modules\aiops\commands;

use humhub\modules\aiops\services\Digest;
use humhub\modules\aiops\services\OperationsManager;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Entrada de linha de comando da camada de IA — o que o cron chama.
 *
 * Todos os comandos saem com codigo 0 mesmo quando algo interno falha. Isso e
 * deliberado: o cron do HumHub roda outras tarefas, e um erro desta camada nao
 * pode derrubar o ciclo de cron da plataforma. A falha vai para o log e para a
 * trilha de auditoria, que sao os lugares certos para observa-la.
 */
class OpsController extends Controller
{
    /**
     * Ciclo de monitoramento. Cron sugerido: a cada 10 minutos.
     * `php protected/yii aiops/monitor`
     */
    public function actionMonitor(): int
    {
        try {
            $module = Yii::$app->getModule('aiops');
            if ($module === null) {
                $this->stdout("aiops: modulo nao habilitado\n");
                return ExitCode::OK;
            }

            $manager = new OperationsManager($module);
            $result = $manager->runCycle('cron');

            $this->stdout(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
        } catch (Throwable $e) {
            Yii::error('aiops/monitor: ' . $e->getMessage(), 'aiops');
            $this->stderr('aiops/monitor falhou: ' . $e->getMessage() . "\n");
        }

        return ExitCode::OK;
    }

    /**
     * Resumo operacional diario. Cron sugerido: uma vez por dia.
     * `php protected/yii aiops/digest`
     */
    public function actionDigest(): int
    {
        try {
            $module = Yii::$app->getModule('aiops');
            if ($module === null) {
                return ExitCode::OK;
            }

            $manager = new OperationsManager($module);
            $digest = new Digest($module, $manager);
            $data = $digest->build();

            $this->stdout($digest->render($data) . "\n");
        } catch (Throwable $e) {
            Yii::error('aiops/digest: ' . $e->getMessage(), 'aiops');
            $this->stderr('aiops/digest falhou: ' . $e->getMessage() . "\n");
        }

        return ExitCode::OK;
    }

    /**
     * Interruptor geral pela linha de comando — util quando a interface esta
     * fora do ar e alguem precisa parar a camada agora.
     * `php protected/yii aiops/kill-switch off`
     */
    public function actionKillSwitch(string $state = 'status'): int
    {
        $module = Yii::$app->getModule('aiops');
        if ($module === null) {
            $this->stderr("aiops: modulo nao habilitado\n");
            return ExitCode::OK;
        }

        if ($state === 'on' || $state === 'off') {
            $module->setEnabled($state === 'on');
            $this->stdout('aiops: camada ' . ($state === 'on' ? 'LIGADA' : 'DESLIGADA') . "\n");
        }

        $this->stdout('estado atual: ' . ($module->isEnabled() ? 'ligada' : 'desligada') . "\n");

        return ExitCode::OK;
    }

    /** Estado da camada, para diagnostico rapido. */
    public function actionStatus(): int
    {
        $module = Yii::$app->getModule('aiops');
        if ($module === null) {
            $this->stderr("aiops: modulo nao habilitado\n");
            return ExitCode::OK;
        }

        $manager = new OperationsManager($module);
        $this->stdout(json_encode([
            'enabled' => $module->isEnabled(),
            'health' => $manager->healthSnapshot(),
            'enforcement_seconds' => $module->getEnforcementSeconds(),
            'min_confidence' => $module->getMinConfidence(),
            'escalation_threshold' => $module->getEscalationThreshold(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");

        return ExitCode::OK;
    }
}
