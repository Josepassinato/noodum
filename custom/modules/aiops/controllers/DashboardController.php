<?php

namespace humhub\modules\aiops\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\aiops\models\AuditEntry;
use humhub\modules\aiops\models\Enforcement;
use humhub\modules\aiops\models\Proposal;
use humhub\modules\aiops\services\AgentCompliance;
use humhub\modules\aiops\services\Digest;
use humhub\modules\aiops\services\OperationsManager;
use Yii;

/**
 * Painel operacional.
 *
 * Herda de admin\components\Controller, que ja exige sessao de administrador —
 * a camada de IA nao inventa controle de acesso proprio.
 */
class DashboardController extends Controller
{
    public function actionIndex()
    {
        $module = Yii::$app->getModule('aiops');
        $manager = new OperationsManager($module);
        $digest = new Digest($module, $manager);

        return $this->render('index', [
            'module' => $module,
            'health' => $manager->healthSnapshot(),
            'digest' => $digest->build(),
            'pending' => Proposal::findPending()->limit(10)->all(),
            'pendingCount' => Proposal::findPending()->count(),
            'enforcements' => Enforcement::findActive()->limit(15)->all(),
            'compliance' => (new AgentCompliance())->summary(),
            'recentAudit' => AuditEntry::find()->orderBy(['id' => SORT_DESC])->limit(25)->all(),
        ]);
    }

    /** Trilha de auditoria completa, com filtro simples por resultado. */
    public function actionAudit(?string $result = null)
    {
        $query = AuditEntry::find()->orderBy(['id' => SORT_DESC]);
        if ($result !== null && $result !== '') {
            $query->andWhere(['result' => $result]);
        }

        return $this->render('audit', [
            'entries' => $query->limit(200)->all(),
            'filter' => $result,
        ]);
    }

    /**
     * Reverte uma contencao autonoma manualmente.
     *
     * Quem reverte fica registrado como ator humano na trilha — a contencao foi
     * decidida pela IA, mas a reversao tem nome e sobrenome.
     */
    public function actionRevert(int $id)
    {
        $this->forcePostRequest();

        $enforcement = Enforcement::findOne($id);
        if ($enforcement === null) {
            throw new \yii\web\NotFoundHttpException();
        }

        $enforcement->revert(Yii::$app->user->id, 'revertido manualmente pela administracao');

        AuditEntry::record([
            'actor_type' => AuditEntry::ACTOR_HUMAN,
            'actor_user_id' => Yii::$app->user->id,
            'reviewer_user_id' => Yii::$app->user->id,
            'trigger' => 'admin_ui',
            'capability' => $enforcement->capability,
            'action' => 'revert_enforcement',
            'result' => AuditEntry::RESULT_REVERTED,
            'subject_type' => $enforcement->subject_type,
            'subject_id' => $enforcement->subject_id,
            'evidence' => json_encode(['enforcement_id' => $enforcement->id], JSON_UNESCAPED_UNICODE),
            'rollback_status' => AuditEntry::ROLLBACK_DONE,
        ]);

        $this->view->saved();

        return $this->redirect(['index']);
    }
}
