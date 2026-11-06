<?php

namespace humhub\modules\aiops\services;

use RuntimeException;
use Yii;

/**
 * Narrow Telegram transport for the private NOODUM owner channel.
 *
 * The bot is deliberately read-only: it can report health, council status,
 * pending proposals and daily digests. It cannot approve proposals, moderate
 * content, publish externally, change infrastructure or access private posts.
 */
final class TelegramGateway
{
    private const API_ROOT = 'https://api.telegram.org';
    private const MESSAGE_LIMIT = 3900;

    /** @param list<string> $allowedChatIds */
    private function __construct(
        private bool $enabled,
        private string $token,
        private string $webhookSecret,
        private array $allowedChatIds,
        private string $webhookUrl
    ) {
    }

    private static function webhookUrl(): string
    {
        $explicit = trim((string)getenv('NOODUM_TELEGRAM_WEBHOOK_URL'));
        if ($explicit !== '') {
            return $explicit;
        }
        $base = rtrim(trim((string)getenv('HUMHUB_BASE_URL')), '/');
        if ($base !== '' && str_starts_with($base, 'https://')) {
            return $base . '/aiops/telegram/webhook';
        }

        return '';
    }

    public static function fromEnvironment(): self
    {
        $rawIds = preg_split('/[\s,]+/', trim((string)getenv('NOODUM_TELEGRAM_ALLOWED_CHAT_IDS'))) ?: [];
        $chatIds = array_values(array_unique(array_filter(
            array_map('trim', $rawIds),
            static fn(string $id): bool => preg_match('/^-?[1-9][0-9]{4,19}$/', $id) === 1
        )));

        return new self(
            filter_var(getenv('NOODUM_TELEGRAM_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
            trim((string)getenv('NOODUM_TELEGRAM_BOT_TOKEN')),
            trim((string)getenv('NOODUM_TELEGRAM_WEBHOOK_SECRET')),
            $chatIds,
            self::webhookUrl()
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isReady(): bool
    {
        return $this->enabled
            && $this->token !== ''
            && $this->validWebhookSecret()
            && $this->allowedChatIds !== []
            && filter_var($this->webhookUrl, FILTER_VALIDATE_URL) !== false
            && str_starts_with($this->webhookUrl, 'https://');
    }

    public function acceptsSecret(string $candidate): bool
    {
        return $this->validWebhookSecret()
            && $candidate !== ''
            && hash_equals($this->webhookSecret, $candidate);
    }

    public function isAllowedChat(string $chatId, string $chatType): bool
    {
        return $chatType === 'private' && in_array($chatId, $this->allowedChatIds, true);
    }

    /** @return array{sent:int,failed:int,disabled?:bool} */
    public function sendToOwners(string $message): array
    {
        if (!$this->isReady()) {
            return ['sent' => 0, 'failed' => 0, 'disabled' => true];
        }

        $sent = 0;
        $failed = 0;
        foreach ($this->allowedChatIds as $chatId) {
            $this->sendMessage($chatId, $message) ? $sent++ : $failed++;
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    public function sendMessage(string $chatId, string $message): bool
    {
        if (!$this->isReady() || !in_array($chatId, $this->allowedChatIds, true)) {
            return false;
        }

        $text = trim($message);
        if ($text === '') {
            return false;
        }
        if (mb_strlen($text) > self::MESSAGE_LIMIT) {
            $text = mb_substr($text, 0, self::MESSAGE_LIMIT - 24) . "\n\n[message truncated]";
        }

        try {
            $this->request('sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'disable_web_page_preview' => true,
            ]);
            return true;
        } catch (RuntimeException $e) {
            Yii::warning('aiops: Telegram delivery failed: ' . $e->getMessage(), 'aiops');
            return false;
        }
    }

    /** @return array<string,mixed> */
    public function setWebhook(): array
    {
        $this->assertReady();

        return $this->request('setWebhook', [
            'url' => $this->webhookUrl,
            'secret_token' => $this->webhookSecret,
            'allowed_updates' => json_encode(['message']),
            'drop_pending_updates' => true,
        ]);
    }

    /** @return array<string,mixed> */
    public function deleteWebhook(): array
    {
        $this->assertToken();

        return $this->request('deleteWebhook', ['drop_pending_updates' => true]);
    }

    /** @return array<string,mixed> */
    public function getWebhookInfo(): array
    {
        $this->assertToken();
        $response = $this->request('getWebhookInfo');
        $result = is_array($response['result'] ?? null) ? $response['result'] : [];

        // Return only operational metadata. Never print certificate material or credentials.
        return [
            'ok' => (bool)($response['ok'] ?? false),
            'url' => (string)($result['url'] ?? ''),
            'pending_update_count' => (int)($result['pending_update_count'] ?? 0),
            'last_error_date' => $result['last_error_date'] ?? null,
            'last_error_message' => $result['last_error_message'] ?? null,
        ];
    }

    private function validWebhookSecret(): bool
    {
        return strlen($this->webhookSecret) >= 32
            && strlen($this->webhookSecret) <= 256
            && preg_match('/^[A-Za-z0-9_-]+$/', $this->webhookSecret) === 1;
    }

    private function assertReady(): void
    {
        if (!$this->isReady()) {
            throw new RuntimeException('integration is disabled or incomplete');
        }
    }

    private function assertToken(): void
    {
        if ($this->token === '') {
            throw new RuntimeException('bot token is not configured');
        }
    }

    /** @return array<string,mixed> */
    private function request(string $method, array $payload = []): array
    {
        $this->assertToken();
        $ch = curl_init(self::API_ROOT . '/bot' . $this->token . '/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
        ]);

        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Telegram API unavailable (HTTP ' . $status . ')' . ($error ? ': ' . $error : ''));
        }

        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
            throw new RuntimeException('Telegram API returned an invalid response');
        }

        return $decoded;
    }
}
