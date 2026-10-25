<?php

namespace RoyalMultiGamers\FirewallOVHGames\Services;

use RoyalMultiGamers\FirewallOVHGames\Exceptions\OvhApiException;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSetting;
use RoyalMultiGamers\FirewallOVHGames\Helpers\PortRangeHelper;
use Illuminate\Support\Facades\Log;

class OvhApiService
{
    protected ?OvhHttpClient $client = null;
    protected ?string $clientFingerprint = null;
    protected array $rulesCache = [];

    /**
     * Always read settings fresh from cache/DB: this service is a singleton and
     * lives as long as the queue worker, so credentials may change underneath it.
     */
    protected function settings(): OvhFirewallSetting
    {
        return OvhFirewallSetting::getInstance();
    }

    /**
     * Get or create the OVH API client.
     * The client is rebuilt whenever the stored credentials change.
     */
    protected function getClient(): OvhHttpClient
    {
        $settings = $this->settings();

        if (!$settings->hasCredentials()) {
            $this->client = null;
            $this->clientFingerprint = null;

            throw OvhApiException::missingCredentials();
        }

        $fingerprint = hash('sha256', implode("\0", [
            $settings->application_key,
            $settings->application_secret,
            $settings->endpoint,
            $settings->consumer_key,
        ]));

        if ($this->client !== null && $this->clientFingerprint === $fingerprint) {
            return $this->client;
        }

        try {
            $this->client = new OvhHttpClient(
                $settings->application_key,
                $settings->application_secret,
                $settings->endpoint,
                $settings->consumer_key
            );
            $this->clientFingerprint = $fingerprint;
            $this->rulesCache = [];

            return $this->client;
        } catch (\Throwable $e) {
            Log::error('Failed to initialize OVH API client', [
                'error' => $e->getMessage(),
                'endpoint' => $settings->endpoint,
            ]);

            throw OvhApiException::connectionFailed($e->getMessage());
        }
    }

    /**
     * Test the connection to OVH API.
     * Uses an authenticated endpoint so invalid credentials are actually detected
     * (/auth/time is public and would always succeed).
     */
    public function testConnection(): bool
    {
        try {
            $this->getClient()->get('/auth/currentCredential');

            return true;
        } catch (\Exception $e) {
            Log::error('OVH API connection test failed', [
                'error' => $e->getMessage(),
            ]);

            throw OvhApiException::authenticationFailed($e->getMessage());
        }
    }

    /**
     * Validate and encode an IP (or CIDR block like 46.105.167.16/30) for use in a URL path.
     */
    protected function encodeIp(string $ip): string
    {
        $parts = explode('/', $ip, 2);
        $address = $parts[0];
        $mask = $parts[1] ?? null;

        $isValidIp = filter_var($address, FILTER_VALIDATE_IP) !== false;
        $maxMask = str_contains($address, ':') ? 128 : 32;
        $isValidMask = $mask === null || (ctype_digit($mask) && (int) $mask <= $maxMask);

        if (!$isValidIp || !$isValidMask) {
            throw OvhApiException::invalidIp($ip);
        }

        return rawurlencode($ip);
    }

    /**
     * Get all firewall rules for a specific IP and IP on Game.
     */
    public function getRules(string $ip, string $ipOnGame): array
    {
        try {
            $client = $this->getClient();
            $encodedIp = $this->encodeIp($ip);
            $encodedIpOnGame = $this->encodeIp($ipOnGame);
            $endpoint = "/ip/{$encodedIp}/game/{$encodedIpOnGame}/rule";
            $cacheKey = $this->getRulesCacheKey($ip, $ipOnGame);

            if (array_key_exists($cacheKey, $this->rulesCache)) {
                return $this->rulesCache[$cacheKey];
            }

            if ($this->shouldLogDetailed()) {
                Log::info('Fetching firewall rules from OVH', [
                    'ip' => $ip,
                    'ip_on_game' => $ipOnGame,
                    'endpoint' => $endpoint,
                ]);
            }

            // Get list of rule IDs
            $ruleIds = $client->get($endpoint);

            if (!is_array($ruleIds)) {
                throw OvhApiException::invalidResponse('Expected array of rule IDs');
            }

            // Fetch details for each rule. A partial list would make the sync
            // diff wrong (duplicate adds), so any failure aborts the whole fetch.
            $rules = [];
            foreach ($ruleIds as $ruleId) {
                if (!is_int($ruleId) && !ctype_digit((string) $ruleId)) {
                    throw OvhApiException::invalidResponse('Unexpected rule ID format');
                }

                $rule = $client->get("{$endpoint}/{$ruleId}");

                if (!is_array($rule)) {
                    throw OvhApiException::invalidResponse("Expected rule details for rule {$ruleId}");
                }

                $rules[] = array_merge(['id' => (int) $ruleId], $rule);
            }

            if ($this->shouldLogDetailed()) {
                Log::info('Successfully fetched firewall rules', [
                    'ip' => $ip,
                    'ip_on_game' => $ipOnGame,
                    'rule_count' => count($rules),
                ]);
            }

            $this->rulesCache[$cacheKey] = $rules;

            return $rules;
        } catch (OvhApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to get firewall rules', [
                'ip' => $ip,
                'ip_on_game' => $ipOnGame,
                'error' => $e->getMessage(),
            ]);

            throw OvhApiException::requestFailed("/ip/{$ip}/game/{$ipOnGame}/rule", $e->getMessage());
        }
    }

    /**
     * Create a new firewall rule.
     */
    public function createRule(string $ip, string $ipOnGame, int $port, ?string $protocol = null): array
    {
        try {
            $client = $this->getClient();
            $encodedIp = $this->encodeIp($ip);
            $encodedIpOnGame = $this->encodeIp($ipOnGame);
            $endpoint = "/ip/{$encodedIp}/game/{$encodedIpOnGame}/rule";

            $protocol = $protocol ?? $this->settings()->default_protocol;
            $portRange = PortRangeHelper::formatPortRange($port);

            if ($this->shouldLogDetailed()) {
                Log::info('Creating firewall rule on OVH', [
                    'ip' => $ip,
                    'ip_on_game' => $ipOnGame,
                    'port' => $port,
                    'port_range' => $portRange,
                    'protocol' => $protocol,
                ]);
            }

            $result = $client->post($endpoint, [
                'ports' => $portRange,
                'protocol' => $protocol,
            ]);

            $this->invalidateRulesCache($ip, $ipOnGame);

            if (!is_array($result)) {
                $result = [];
            }

            if ($this->shouldLogDetailed()) {
                Log::info('Successfully created firewall rule', [
                    'ip' => $ip,
                    'ip_on_game' => $ipOnGame,
                    'port' => $port,
                    'rule_id' => $result['id'] ?? 'unknown',
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to create firewall rule', [
                'ip' => $ip,
                'ip_on_game' => $ipOnGame,
                'port' => $port,
                'error' => $e->getMessage(),
            ]);

            if ($e instanceof OvhApiException) {
                throw $e;
            }

            throw OvhApiException::requestFailed("/ip/{$ip}/game/{$ipOnGame}/rule", $e->getMessage());
        }
    }

    /**
     * Delete a firewall rule.
     */
    public function deleteRule(string $ip, string $ipOnGame, int $ruleId): bool
    {
        try {
            $client = $this->getClient();
            $encodedIp = $this->encodeIp($ip);
            $encodedIpOnGame = $this->encodeIp($ipOnGame);
            $endpoint = "/ip/{$encodedIp}/game/{$encodedIpOnGame}/rule/{$ruleId}";

            if ($this->shouldLogDetailed()) {
                Log::info('Deleting firewall rule on OVH', [
                    'ip' => $ip,
                    'ip_on_game' => $ipOnGame,
                    'rule_id' => $ruleId,
                ]);
            }

            $client->delete($endpoint);

            $this->invalidateRulesCache($ip, $ipOnGame);

            if ($this->shouldLogDetailed()) {
                Log::info('Successfully deleted firewall rule', [
                    'ip' => $ip,
                    'ip_on_game' => $ipOnGame,
                    'rule_id' => $ruleId,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete firewall rule', [
                'ip' => $ip,
                'ip_on_game' => $ipOnGame,
                'rule_id' => $ruleId,
                'error' => $e->getMessage(),
            ]);

            if ($e instanceof OvhApiException) {
                throw $e;
            }

            throw OvhApiException::requestFailed("/ip/{$ip}/game/{$ipOnGame}/rule/{$ruleId}", $e->getMessage());
        }
    }

    /**
     * Get details of a specific firewall rule.
     */
    public function getRule(string $ip, string $ipOnGame, int $ruleId): array
    {
        try {
            $client = $this->getClient();
            $encodedIp = $this->encodeIp($ip);
            $encodedIpOnGame = $this->encodeIp($ipOnGame);
            $endpoint = "/ip/{$encodedIp}/game/{$encodedIpOnGame}/rule/{$ruleId}";

            $rule = $client->get($endpoint);

            if (!is_array($rule)) {
                throw OvhApiException::invalidResponse("Expected rule details for rule {$ruleId}");
            }

            return array_merge(['id' => $ruleId], $rule);
        } catch (\Exception $e) {
            Log::error('Failed to get firewall rule details', [
                'ip' => $ip,
                'ip_on_game' => $ipOnGame,
                'rule_id' => $ruleId,
                'error' => $e->getMessage(),
            ]);

            if ($e instanceof OvhApiException) {
                throw $e;
            }

            throw OvhApiException::requestFailed("/ip/{$ip}/game/{$ipOnGame}/rule/{$ruleId}", $e->getMessage());
        }
    }

    /**
     * Find a rule by port.
     * API errors are propagated: returning null on failure would make callers
     * believe the rule is absent and create duplicates or skip deletions.
     */
    public function findRuleByPort(string $ip, string $ipOnGame, int $port): ?array
    {
        foreach ($this->getRules($ip, $ipOnGame) as $rule) {
            if (isset($rule['ports']) && PortRangeHelper::getSinglePort($rule['ports']) === $port) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Check if a rule exists for a specific port.
     */
    public function ruleExistsForPort(string $ip, string $ipOnGame, int $port): bool
    {
        return $this->findRuleByPort($ip, $ipOnGame, $port) !== null;
    }

    /**
     * Drop all cached rule lists. Call at the start of each top-level operation
     * so a long-running worker never reuses rules fetched by a previous job.
     */
    public function flushRulesCache(): void
    {
        $this->rulesCache = [];
    }

    protected function getRulesCacheKey(string $ip, string $ipOnGame): string
    {
        return $ip . '|' . $ipOnGame;
    }

    protected function invalidateRulesCache(string $ip, string $ipOnGame): void
    {
        unset($this->rulesCache[$this->getRulesCacheKey($ip, $ipOnGame)]);
    }

    protected function shouldLogDetailed(): bool
    {
        return (bool) config('firewall-ovh-games.logging.enabled', false);
    }
}
