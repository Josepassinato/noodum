<?php

namespace humhub\modules\aiops;

use humhub\modules\aiops\models\Proposal;
use humhub\modules\aiops\services\Digest;
use humhub\modules\aiops\services\OperationsManager;
use Throwable;
use Yii;
use yii\helpers\Url;

class Events
{
    /**
     * Ciclo horario: reforca o monitoramento mesmo que o laco curto do
     * container de cron esteja parado. Redundancia proposital — a camada de
     * observacao nao deve depender de um unico agendador.
     */
    public static function onHourlyCron($event)
    {
        self::guarded(static function () {
            $module = Yii::$app->getModule('aiops');
            if ($module !== null && $module->isEnabled()) {
                (new OperationsManager($module))->runCycle('cron_hourly');
            }
        }, 'onHourlyCron');
    }

    /** Resumo operacional diario. */
    public static function onDailyCron($event)
    {
        self::guarded(static function () {
            $module = Yii::$app->getModule('aiops');
            if ($module === null || !$module->isEnabled()) {
                return;
            }
            $manager = new OperationsManager($module);
            $digest = new Digest($module, $manager);
            Yii::info("aiops digest\n" . $digest->render($digest->build()), 'aiops');
        }, 'onDailyCron');
    }

    /**
     * Qualquer falha desta camada fica contida aqui.
     *
     * O cron do HumHub roda outras tarefas da plataforma no mesmo processo; uma
     * excecao vinda daqui interromperia o restante do ciclo. A rede social tem
     * precedencia sobre a camada de IA.
     */
    private static function guarded(callable $fn, string $context): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            Yii::error('aiops: ' . $context . ' falhou: ' . $e->getMessage(), 'aiops');
        }
    }

    /**
     * Item de menu na administracao. O contador de propostas pendentes fica
     * visivel no rotulo de proposito: uma fila de aprovacao que ninguem ve e
     * uma fila que caduca sozinha, e proposta caducada nao executa nada.
     */
    public static function onAdminMenuInit($event)
    {
        if (!Yii::$app->user->isAdmin()) {
            return;
        }

        $pending = 0;
        try {
            $pending = (int)Proposal::findPending()->count();
        } catch (\Throwable) {
            // Modulo recem-instalado, migracao ainda nao rodou: o menu aparece
            // sem contador em vez de quebrar a administracao inteira.
        }

        $event->sender->addItem([
            'label' => 'Operacao IA' . ($pending > 0 ? ' (' . $pending . ')' : ''),
            'url' => Url::to(['/aiops/dashboard']),
            'group' => 'manage',
            'icon' => 'robot',
            'isActive' => Yii::$app->controller->module
                && Yii::$app->controller->module->id === 'aiops',
            'sortOrder' => 350,
        ]);
    }
}
