<?php

namespace humhub\modules\aiops\services\llm;

/**
 * Fronteira com o provedor de modelo.
 *
 * O modelo e usado apenas para classificar e resumir. Ele nunca decide acao:
 * `classify()` devolve um rotulo de uma lista fechada que o chamador informa, e
 * qualquer coisa fora dessa lista e descartada pela implementacao.
 *
 * Toda implementacao deve degradar em silencio: se o provedor estiver fora do
 * ar, `classify()` devolve null e `summarize()` devolve null. Nunca lanca
 * excecao para o worker, e nunca "chuta" um rotulo.
 */
interface LlmAdapter
{
    /**
     * Classifica um texto nao confiavel dentro de um conjunto fechado.
     *
     * @param string $text texto ja sanitizado e envelopado
     * @param string[] $labels rotulos permitidos; o retorno DEVE ser um deles
     * @return array{label:string,confidence:float}|null null = indisponivel ou resposta invalida
     */
    public function classify(string $text, array $labels, string $instruction): ?array;

    /**
     * Resume um conjunto de fatos ja apurados de forma deterministica.
     * O resumo e texto para leitura humana — nunca vira acao.
     */
    public function summarize(string $text, string $instruction): ?string;

    /** Identificacao do provedor/modelo para a trilha de auditoria. */
    public function describe(): string;

    public function isAvailable(): bool;
}
