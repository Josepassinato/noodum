<?php

namespace humhub\modules\aiops\services\llm;

/**
 * Adaptador usado quando nao ha provedor configurado.
 *
 * E o padrao do modulo, de proposito: sem chave de API, a camada de IA roda
 * inteira em regras deterministicas. Nada de moderacao deixa de funcionar por
 * falta de modelo — apenas as tarefas que dependem de linguagem (classificacao
 * ambigua, resumo em prosa) sao puladas.
 */
final class NullAdapter implements LlmAdapter
{
    public function classify(string $text, array $labels, string $instruction): ?array
    {
        return null;
    }

    public function summarize(string $text, string $instruction): ?string
    {
        return null;
    }

    public function describe(): string
    {
        return 'null (nenhum provedor configurado)';
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
