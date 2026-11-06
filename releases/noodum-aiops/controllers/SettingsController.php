<?php

namespace humhub\modules\aiops\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\aiops\components\Governance;
use humhub\modules\aiops\services\OperationsManager;
use Yii;

/**
 * Interruptor geral, interruptores por capacidade e limites operacionais.
 *
 * Capacidades de nivel 3 aparecem na tela apenas para leitura, sem controle
 * para liga-las. Nao existe interruptor porque nao existe executor — mostrar a
 * lista deixa explicito o que a IA nao alcanca.
 */
class SettingsController extends Controller
{
    public function actionIndex()
    {
        $module = Yii::$app->getModule('aiops');
        $request = Yii::$app->request;

        if ($request->isPost) {
            $module->setEnabled((bool)$request->post('enabled', 0));

            foreach (array_keys(Governance::AUTONOMOUS + Governance::PROPOSAL) as $capability) {
                $module->setCapabilityEnabled($capability, (bool)$request->post('cap_' . $capability, 0));
            }

            $module->settings->set('enforcement_seconds', (int)$request->post('enforcement_seconds', 3600));
            $module->settings->set('min_confidence', (float)$request->post('min_confidence', 0.75));
            $module->settings->set('escalation_threshold', (int)$request->post('escalation_threshold', 3));
            $module->settings->set('allowlist', (string)$request->post('allowlist', ''));
            $module->settings->set('denylist', (string)$request->post('denylist', ''));

            $this->view->saved();

            return $this->redirect(['index']);
        }

        $manager = new OperationsManager($module);

        return $this->render('index', [
            'module' => $module,
            'llm' => $manager->getLlm()->describe(),
            'llmAvailable' => $manager->getLlm()->isAvailable(),
        ]);
    }
}
