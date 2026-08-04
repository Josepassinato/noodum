<?php

namespace humhub\modules\aiops\services\llm;

/**
 * Three-provider council for bounded operational decisions.
 *
 * Each member has an independent credential, model and endpoint. A model can
 * only vote for a caller-provided closed label. No single provider can create
 * an actionable classification: at least two distinct providers must agree.
 */
final class CouncilAdapter implements LlmAdapter
{
    public const ROLE_OPENAI = 'openai_product_and_technical';
    public const ROLE_XAI = 'grok_growth_and_counterpoint';
    public const ROLE_GEMINI = 'gemini_safety_and_community';

    /** @var array<string,LlmAdapter> */
    private array $members;

    /** @param array<string,LlmAdapter> $members */
    public function __construct(array $members, private int $quorum = 2)
    {
        $this->members = array_filter(
            $members,
            static fn($member): bool => $member instanceof LlmAdapter
        );
        $this->quorum = max(2, min($this->quorum, 3));
    }

    public static function fromEnvironment(): self
    {
        $members = [];

        self::addEnvironmentMember(
            $members,
            self::ROLE_OPENAI,
            'AIOPS_OPENAI',
            'https://api.openai.com/v1'
        );
        self::addEnvironmentMember(
            $members,
            self::ROLE_XAI,
            'AIOPS_XAI',
            'https://api.x.ai/v1'
        );
        self::addEnvironmentMember(
            $members,
            self::ROLE_GEMINI,
            'AIOPS_GEMINI',
            'https://generativelanguage.googleapis.com/v1beta/openai'
        );

        return new self($members);
    }

    /** @param array<string,LlmAdapter> $members */
    private static function addEnvironmentMember(
        array &$members,
        string $role,
        string $prefix,
        string $defaultBaseUrl
    ): void {
        $apiKey = trim((string)getenv($prefix . '_API_KEY'));
        $model = trim((string)getenv($prefix . '_MODEL'));

        // Both are required. A stale or guessed model name must not silently
        // turn into a different provider's default.
        if ($apiKey === '' || $model === '') {
            return;
        }

        $baseUrl = trim((string)getenv($prefix . '_BASE_URL')) ?: $defaultBaseUrl;
        $members[$role] = new OpenAiCompatibleAdapter(
            $apiKey,
            $baseUrl,
            $model,
            20,
            $role
        );
    }

    public function configuredCount(): int
    {
        return count($this->members);
    }

    /** @return array<string,string> */
    public function memberStatus(): array
    {
        $status = [];
        foreach ($this->members as $role => $member) {
            $status[$role] = $member->isAvailable() ? $member->describe() : 'unavailable';
        }

        return $status;
    }

    public function classify(string $text, array $labels, string $instruction): ?array
    {
        if (!$this->isAvailable() || $labels === []) {
            return null;
        }

        $votes = [];
        foreach ($this->members as $role => $member) {
            if (!$member->isAvailable()) {
                continue;
            }

            $vote = $member->classify(
                $text,
                $labels,
                $instruction . "\n\nCouncil role: {$role}. Vote independently; do not imitate another member."
            );
            if ($vote !== null) {
                $votes[$role] = $vote;
            }
        }

        $byLabel = [];
        foreach ($votes as $role => $vote) {
            $byLabel[$vote['label']][$role] = $vote;
        }
        uasort($byLabel, static fn(array $a, array $b): int => count($b) <=> count($a));

        $winningLabel = array_key_first($byLabel);
        if ($winningLabel === null || count($byLabel[$winningLabel]) < $this->quorum) {
            return null;
        }

        $winningVotes = $byLabel[$winningLabel];
        $confidence = array_sum(array_column($winningVotes, 'confidence')) / count($winningVotes);

        return [
            'label' => $winningLabel,
            'confidence' => max(0.0, min($confidence, 1.0)),
            'votes' => $votes,
            'quorum' => count($winningVotes),
        ];
    }

    public function summarize(string $text, string $instruction): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $perspectives = [];
        foreach ($this->members as $role => $member) {
            if (!$member->isAvailable()) {
                continue;
            }
            $summary = $member->summarize(
                $text,
                $instruction . "\n\nYour council role is {$role}. State evidence, risks and a bounded recommendation."
            );
            if ($summary !== null) {
                $perspectives[$role] = $summary;
            }
        }

        if (count($perspectives) < $this->quorum) {
            return null;
        }

        $sections = [];
        foreach ($perspectives as $role => $summary) {
            $sections[] = "## {$role}\n{$summary}";
        }

        return implode("\n\n", $sections);
    }

    public function describe(): string
    {
        return sprintf(
            'provider council: %d/3 configured, quorum %d',
            $this->configuredCount(),
            $this->quorum
        );
    }

    public function isAvailable(): bool
    {
        $available = array_filter(
            $this->members,
            static fn(LlmAdapter $member): bool => $member->isAvailable()
        );

        return count($available) >= $this->quorum;
    }
}
