<?php

use humhub\components\Migration;

/**
 * Memoria do gerente da plataforma.
 *
 * Duas tabelas, dois papeis distintos:
 *  - aiops_conversation  historico do canal do dono, para o gerente ter fio da meada
 *  - aiops_direction     direcoes e ideias que o dono deu, para o conselho ler no ciclo
 *
 * Nenhuma das duas autoriza acao. Direcao registrada e insumo de briefing, nao
 * ordem de execucao: o que muda a plataforma continua passando por proposta e
 * aprovacao humana no painel autenticado.
 */
class m260804_193000_manager extends Migration
{
    public function safeUp()
    {
        $this->createTable('aiops_conversation', [
            'id' => $this->primaryKey(),
            'chat_id' => $this->string(32)->notNull(),
            // 'owner' ou 'manager'. Explicito para que o historico responda quem
            // falou sem depender da ordem das linhas.
            'role' => $this->string(16)->notNull(),
            'content' => $this->text()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_aiops_conv_chat', 'aiops_conversation', ['chat_id', 'id']);

        $this->createTable('aiops_direction', [
            'id' => $this->primaryKey(),
            'chat_id' => $this->string(32)->notNull(),
            'content' => $this->text()->notNull(),
            // open -> ainda nao entrou em briefing; acknowledged -> conselho ja leu.
            'status' => $this->string(16)->notNull()->defaultValue('open'),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_aiops_direction_status', 'aiops_direction', ['status', 'created_at']);
    }

    public function safeDown()
    {
        $this->dropTable('aiops_direction');
        $this->dropTable('aiops_conversation');

        return true;
    }
}
