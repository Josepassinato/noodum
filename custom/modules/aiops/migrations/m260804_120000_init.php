<?php

use humhub\components\Migration;

/**
 * Estruturas da camada de operacao por IA.
 *
 * Tres tabelas, tres papeis distintos:
 *  - aiops_audit       trilha de tudo que a IA observou, propos ou fez
 *  - aiops_proposal    fila de aprovacao humana (governanca nivel 2)
 *  - aiops_enforcement contencoes autonomas, sempre reversiveis e com prazo
 */
class m260804_120000_init extends Migration
{
    public function safeUp()
    {
        $this->createTable('aiops_audit', [
            'id' => $this->primaryKey(),
            // Quem agiu: 'ai' ou 'human'. Explicito para que a trilha responda
            // "foi a IA ou foi uma pessoa?" sem depender de inferencia.
            'actor_type' => $this->string(16)->notNull()->defaultValue('ai'),
            'actor_user_id' => $this->integer()->null(),
            'trigger' => $this->string(64)->notNull(),
            'capability' => $this->string(64)->notNull(),
            'governance_level' => $this->tinyInteger()->notNull(),
            'action' => $this->string(64)->notNull(),
            'result' => $this->string(32)->notNull(),
            'subject_type' => $this->string(32)->null(),
            'subject_id' => $this->integer()->null(),
            'evidence' => $this->text()->null(),
            'confidence' => $this->decimal(4, 3)->null(),
            'rollback_status' => $this->string(24)->notNull()->defaultValue('not_applicable'),
            'reviewer_user_id' => $this->integer()->null(),
            'proposal_id' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_aiops_audit_created', 'aiops_audit', 'created_at');
        $this->createIndex('idx_aiops_audit_capability', 'aiops_audit', 'capability');
        $this->createIndex('idx_aiops_audit_subject', 'aiops_audit', ['subject_type', 'subject_id']);

        $this->createTable('aiops_proposal', [
            'id' => $this->primaryKey(),
            'capability' => $this->string(64)->notNull(),
            'status' => $this->string(24)->notNull()->defaultValue('pending'),
            'subject_type' => $this->string(32)->null(),
            'subject_id' => $this->integer()->null(),
            'reason' => $this->text()->notNull(),
            'evidence' => $this->text()->null(),
            'confidence' => $this->decimal(4, 3)->null(),
            'proposed_action' => $this->string(255)->notNull(),
            'impact' => $this->text()->null(),
            'expires_at' => $this->dateTime()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'decided_at' => $this->dateTime()->null(),
            'decided_by' => $this->integer()->null(),
            'decision_note' => $this->text()->null(),
        ]);
        $this->createIndex('idx_aiops_proposal_status', 'aiops_proposal', ['status', 'expires_at']);
        // Impede que o worker empilhe a mesma proposta a cada ciclo enquanto o
        // humano nao decide. Sem isto a fila vira ruido e perde utilidade.
        $this->createIndex(
            'idx_aiops_proposal_dedup',
            'aiops_proposal',
            ['capability', 'subject_type', 'subject_id', 'status']
        );

        $this->createTable('aiops_enforcement', [
            'id' => $this->primaryKey(),
            'capability' => $this->string(64)->notNull(),
            'subject_type' => $this->string(32)->notNull(),
            'subject_id' => $this->integer()->notNull(),
            'reason' => $this->text()->notNull(),
            'evidence' => $this->text()->null(),
            // NOT NULL de proposito: nao existe contencao autonoma sem prazo.
            'expires_at' => $this->dateTime()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'reverted_at' => $this->dateTime()->null(),
            'reverted_by' => $this->integer()->null(),
            'revert_reason' => $this->string(255)->null(),
        ]);
        $this->createIndex('idx_aiops_enf_active', 'aiops_enforcement', ['subject_type', 'subject_id', 'reverted_at']);
        $this->createIndex('idx_aiops_enf_expiry', 'aiops_enforcement', 'expires_at');
    }

    public function safeDown()
    {
        $this->dropTable('aiops_enforcement');
        $this->dropTable('aiops_proposal');
        $this->dropTable('aiops_audit');
    }
}
