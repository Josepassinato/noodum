<?php

namespace humhub\modules\aiops\components;

/**
 * Defesa contra injecao de prompt vinda de conteudo de usuario.
 *
 * Premissa: TODO texto publicado por usuario e dado nao confiavel. Ele nunca
 * vira instrucao. As tres barreiras, em ordem:
 *
 *  1. O conteudo do usuario nunca entra na mensagem de sistema — vai sempre no
 *     turno de usuario, dentro de um envelope delimitado (ver envelope()).
 *  2. Marcadores conhecidos de sequestro de instrucao sao neutralizados antes
 *     de sair daqui (neutralize()).
 *  3. A saida do modelo e validada contra um conjunto fechado de rotulos
 *     (ver OpenAiCompatibleAdapter::classify); texto livre nunca vira acao.
 *
 * A barreira que realmente sustenta o sistema e a 3: mesmo que 1 e 2 falhem, o
 * modelo nao consegue pedir uma acao que o executor aceite, porque o executor
 * so aceita rotulo de lista fixa e so executa capacidade de nivel 1.
 */
final class Sanitizer
{
    /** Limite de texto enviado ao modelo, por item. */
    public const MAX_CHARS = 2000;

    /**
     * Padroes de sequestro de instrucao. Nao pretendem ser exaustivos — sao a
     * segunda barreira, nao a principal.
     */
    private const INJECTION_PATTERNS = [
        '/ignore\s+(all\s+)?(previous|prior|above)\s+instructions?/iu',
        '/disregard\s+(all\s+)?(previous|prior|above)/iu',
        '/ignore\s+as\s+instrucoes\s+(anteriores|acima)/iu',
        '/desconsidere\s+(as\s+)?instrucoes/iu',
        '/esqueca\s+(as\s+)?(instrucoes|regras)\s+(anteriores|acima)/iu',
        '/you\s+are\s+now\s+(a|an)\s+/iu',
        '/voce\s+agora\s+e\s+(um|uma)\s+/iu',
        '/act\s+as\s+(a\s+)?(system|admin|administrator|developer)/iu',
        '/aja\s+como\s+(um\s+)?(sistema|admin|administrador)/iu',
        '/\bsystem\s*:/iu',
        '/\bassistant\s*:/iu',
        '/<\|.*?\|>/u',
        '/\[\/?INST\]/iu',
        '/```\s*system/iu',
        '/new\s+instructions?\s*:/iu',
        '/novas?\s+instrucoes?\s*:/iu',
        '/reveal\s+(your\s+)?(system\s+)?prompt/iu',
        '/mostre\s+(seu\s+)?prompt/iu',
    ];

    /**
     * Remove marcadores de instrucao e delimitadores que poderiam fechar o
     * envelope. Preserva o sentido do texto para a classificacao — o objetivo e
     * desarmar, nao censurar.
     */
    public static function neutralize(string $text): string
    {
        $clean = preg_replace(self::INJECTION_PATTERNS, '[conteudo removido pela sanitizacao]', $text) ?? $text;

        // Impede que o texto feche o envelope e escape para fora dele.
        $clean = str_replace(['<<<CONTEUDO', 'CONTEUDO>>>'], '', $clean);

        // Normaliza controles invisiveis usados para esconder instrucao.
        $clean = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}]/u', '', $clean) ?? $clean;

        if (mb_strlen($clean) > self::MAX_CHARS) {
            $clean = mb_substr($clean, 0, self::MAX_CHARS) . ' […truncado]';
        }

        return trim($clean);
    }

    /**
     * Empacota conteudo nao confiavel num envelope explicito, deixando claro ao
     * modelo que aquilo e material a ser analisado e nao ordem a ser cumprida.
     */
    public static function envelope(string $text): string
    {
        return "<<<CONTEUDO\n" . self::neutralize($text) . "\nCONTEUDO>>>";
    }

    /**
     * Heuristica deterministica: o texto tenta sequestrar instrucao?
     *
     * Usada para sinalizar o item para revisao humana. Nao dispara punicao
     * sozinha — tentativa de injecao pode ser discussao legitima sobre o tema
     * numa rede que fala justamente sobre IA.
     */
    public static function looksLikeInjection(string $text): bool
    {
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
