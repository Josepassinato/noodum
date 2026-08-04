<?php

namespace humhub\modules\aiops\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\aiops\components\Governance;
use humhub\modules\aiops\models\AuditEntry;
use humhub\modules\aiops\models\Proposal;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Fila de aprovacao humana (governanca nivel 2).
 *
 * Ponto importante: aprovar aqui registra a DECISAO, nao dispara execucao
 * automatica no HumHub. A acao concreta (suspender, remover, encerrar) e
 * praticada pelo administrador nas telas nativas da plataforma.
 *
 * Isso e escolha de projeto, nao limitacao: se a aprovacao executasse sozinha,
 * um clique errado numa fila cheia viraria acao irreversivel sem confirmacao —
 * exatamente o risco que a governanca em niveis existe para evitar. A fila
 * entrega contexto e evidencia; a mao que executa continua sendo humana.
 */
class ApprovalController extends Controller
{
    public function actionIndex(string $status = Proposal::STATUS_PENDING)
    {
        $query = Proposal::find()->orderBy(['created_at' => SORT_DESC]);
        if ($status !== 'all') {
            $query->andWhere(['status' => $status]);
        }

        return $this->render('index', [
            'proposals' => $query->limit(100)->all(),
            'status' => $status,
            'pendingCount' => Proposal::findPending()->count(),
        ]);
    }

    public function actionView(int $id)
    {
        return $this->render('view', ['proposal' => $this->loadProposal($id)]);
    }

    public function actionApprove(int $id)
    {
        return $this->decide($id, Proposal::STATUS_APPROVED, AuditEntry::RESULT_APPROVED);
    }

    public function actionReject(int $id)
    {
        return $this->decide($id, Proposal::STATUS_REJECTED, AuditEntry::RESULT_REJECTED);
    }

    private function decide(int $id, string $status, string $auditResult)
    {
        $this->forcePostRequest();
        $proposal = $this->loadProposal($id);

        if ($proposal->status !== Proposal::STATUS_PENDING) {
            $this->view->error('Esta proposta ja foi decidida.');
            return $this->redirect(['index']);
        }

        // Proposta vencida nao pode ser aprovada retroativamente: o prazo
        // existe justamente porque o contexto envelhece.
        if ($proposal->isExpired()) {
            $proposal->status = Proposal::STATUS_EXPIRED;
            $proposal->decided_at = gmdate('Y-m-d H:i:s');
            $proposal->save(false);
            $this->view->error('Proposta expirada; nenhuma acao foi executada.');
            return $this->redirect(['index']);
        }

        $proposal->status = $status;
        $proposal->decided_at = gmdate('Y-m-d H:i:s');
        $proposal->decided_by = Yii::$app->user->id;
        $proposal->decision_note = (string)Yii::$app->request->post('note', '');
        $proposal->save(false);

        AuditEntry::record([
            'actor_type' => AuditEntry::ACTOR_HUMAN,
            'actor_user_id' => Yii::$app->user->id,
            'reviewer_user_id' => Yii::$app->user->id,
            'trigger' => 'admin_ui',
            'capability' => $proposal->capability,
            'action' => 'decide_proposal',
            'result' => $auditResult,
            'subject_type' => $proposal->subject_type,
            'subject_id' => $proposal->subject_id,
            'proposal_id' => $proposal->id,
            'confidence' => $proposal->confidence,
            'evidence' => json_encode([
                'decision' => $status,
                'note' => $proposal->decision_note,
                'governance_level' => Governance::levelFor($proposal->capability),
                'execution' => 'decisao registrada; a acao concreta e praticada pelo administrador nas telas nativas',
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $this->view->saved();

        return $this->redirect(['index']);
    }

    private function loadProposal(int $id): Proposal
    {
        $proposal = Proposal::findOne($id);
        if ($proposal === null) {
            throw new NotFoundHttpException('Proposta nao encontrada.');
        }

        return $proposal;
    }
}
