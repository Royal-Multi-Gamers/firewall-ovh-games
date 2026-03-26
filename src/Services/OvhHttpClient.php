<?php

namespace RoyalMultiGamers\FirewallOVHGames\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RoyalMultiGamers\FirewallOVHGames\Exceptions\OvhApiException;

class OvhHttpClient
{
    protected string $applicationKey;
    protected string $applicationSecret;
    protected string $consumerKey;
    protected string $baseUrl;

    protected ?int $timeOffset = null;
    protected int $timeOffsetFetchedAt = 0;

    public function __construct(string $applicationKey, string $applicationSecret, string $endpoint, string $consumerKey)
    {
        $this->applicationKey = $applicationKey;
        $this->applicationSecret = $applicationSecret;
        $this->consumerKey = $consumerKey;
        $this->baseUrl = rtrim($this->resolveBaseUrl($endpoint), '/');
    }

    public function get(string $path): mixed
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $payload): mixed
    {
        return $this->request('POST', $path, $payload);
    }

    public function delete(string $path): mixed
    {
        return $this->request('DELETE', $path);
    }

    protected function request(string $method, string $path, array $payload = []): mixed
    {
        $path = '/' . ltrim($path, '/');
        $url = $this->baseUrl . $path;
        $body = $payload === [] ? '' : (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = $this->getTimestamp();

        $signatureBase = implode('+', [
            $this->applicationSecret,
            $this->consumerKey,
            strtoupper($method),
            $url,
            $body,
            (string) $timestamp,
        ]);

        $signature = '$1$' . sha1($signatureBase);

        $request = Http::acceptJson()
            ->withHeaders([
                'X-Ovh-Application' => $this->applicationKey,
                'X-Ovh-Consumer' => $this->consumerKey,
                'X-Ovh-Timestamp' => (string) $timestamp,
                'X-Ovh-Signature' => $signature,
            ])
            ->timeout(20)
            ->connectTimeout(5);

        if ($body !== '') {
            $request = $request->withBody($body, 'application/json');
        }

        $response = $request->send(strtoupper($method), $url);

        return $this->decodeResponse($response, $path);
    }

    protected function decodeResponse(Response $response, string $path): mixed
    {
        if ($response->successful()) {
            if (strtoupper($response->header('Content-Type', '')) && str_contains((string) $response->header('Content-Type', ''), 'application/json')) {
                return $response->json();
            }

            $json = $response->json();
            if ($json !== null) {
                return $json;
            }

            return $response->body();
        }

        $payload = $response->json();
        $reason = is_array($payload)
            ? ($payload['message'] ?? $payload['error'] ?? $response->body())
            : $response->body();

        if ($response->status() === 429) {
            throw OvhApiException::rateLimitExceeded();
        }

        throw OvhApiException::requestFailed($path, trim((string) $reason));
    }

    protected function resolveBaseUrl(string $endpoint): string
    {
        return match ($endpoint) {
            'ovh-eu' => 'https://eu.api.ovh.com/1.0',
            'ovh-ca' => 'https://ca.api.ovh.com/1.0',
            'ovh-us' => 'https://api.us.ovhcloud.com/1.0',
            'soyoustart-eu' => 'https://eu.api.soyoustart.com/1.0',
            'soyoustart-ca' => 'https://ca.api.soyoustart.com/1.0',
            'kimsufi-eu' => 'https://eu.api.kimsufi.com/1.0',
            'kimsufi-ca' => 'https://ca.api.kimsufi.com/1.0',
            default => 'https://eu.api.ovh.com/1.0',
        };
    }

    protected function getTimestamp(): int
    {
        if ($this->timeOffset !== null && (time() - $this->timeOffsetFetchedAt) < 300) {
            return time() + $this->timeOffset;
        }

        $response = Http::timeout(10)->connectTimeout(5)->get($this->baseUrl . '/auth/time');

        if ($response->failed()) {
            return time();
        }

        $remoteTime = (int) trim((string) $response->body());
        if ($remoteTime > 0) {
            $this->timeOffset = $remoteTime - time();
            $this->timeOffsetFetchedAt = time();

            return $remoteTime;
        }

        return time();
    }
}