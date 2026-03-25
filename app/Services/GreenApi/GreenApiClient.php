<?php

namespace App\Services\GreenApi;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;

class GreenApiClient
{
    /**
     * @param  array{url: string, instance_id: string|null, token: string|null, webhook_token: string|null}|null  $config
     */
    public function __construct(
        protected HttpFactory $http,
        protected ?array $config = null,
    ) {
        $this->config ??= config('services.green_api');
    }

    public function isConfigured(): bool
    {
        return filled($this->instanceId()) && filled($this->token());
    }

    /**
     * @return array{idMessage: string}
     */
    public function sendMessage(string $chatId, string $message): array
    {
        $response = $this->http
            ->baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry([200, 500], throw: false)
            ->post($this->messageEndpoint(), [
                'chatId' => $chatId,
                'message' => $message,
            ]);

        $response->throw();

        $payload = $response->json();

        if (! is_array($payload) || blank(Arr::get($payload, 'idMessage'))) {
            throw new \RuntimeException('Green API did not return a message id.');
        }

        return [
            'idMessage' => (string) Arr::get($payload, 'idMessage'),
        ];
    }

    public function webhookToken(): ?string
    {
        $token = Arr::get($this->config, 'webhook_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    protected function baseUrl(): string
    {
        return rtrim((string) Arr::get($this->config, 'url', 'https://api.green-api.com'), '/');
    }

    protected function instanceId(): ?string
    {
        $instanceId = Arr::get($this->config, 'instance_id');

        return is_scalar($instanceId) && $instanceId !== '' ? (string) $instanceId : null;
    }

    protected function token(): ?string
    {
        $token = Arr::get($this->config, 'token');

        return is_scalar($token) && $token !== '' ? (string) $token : null;
    }

    protected function messageEndpoint(): string
    {
        return sprintf('/waInstance%s/sendMessage/%s', $this->instanceId(), $this->token());
    }
}
