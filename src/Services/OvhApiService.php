<?php

namespace RoyalMultiGamers\FirewallOVHGames\Services;

use RoyalMultiGamers\FirewallOVHGames\Exceptions\OvhApiException;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSetting;
use RoyalMultiGamers\FirewallOVHGames\Helpers\PortRangeHelper;
use Illuminate\Support\Facades\Log;

class OvhApiService
{
    protected ?OvhHttpClient $client = null;
    protected ?OvhFirewallSetting $settings = null;
    protected array $rulesCache = [];

    /**
     * Initialize the OVH API client.
     */
    public function __construct()
    {
        $this->settings = OvhFirewallSetting::getInstance();
    }

    /**
     * Get or create the OVH API client.
     */
    protected function getClient(): OvhHttpClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if (!$this->settings->hasCredentials()) {
            throw OvhApiException::missingCredentials();
        }

        try {
            $this->client = new OvhHttpClient(
                $this->settings->application_key,
                $this->settings->application_secret,
                $this->settings->endpoint,
                $this->settings->consumer_key
            );

            return $this->client;
        } catch (\Throwable $e) {
            Log::error('Failed to initialize OVH API client', [
                'error' => $e->getMessage(),
                'endpoint' => $this->settings->endpoint,
            ]);

            throw OvhApiException::connectionFailed($e->getMessage());
        }
    }

    /**
     * Test the connection to OVH API.
     */
    public function testConnection(): bool
    {
        try {
            $client = $this->getClient();
            
            // Try to get the current time from OVH API as a simple test
            $client->get('/auth/time');
            
            return true;
        } catch (\Exception $e) {
            Log::error('OVH API connection test failed', [
                'error' => $e->getMessage(),
            ]);

            throw OvhApiException::authenticationFailed($e->getMessage());
        }
    }

    /**
     * Encode IP for URL (handle CIDR blocks like 46.105.167.16/30).
     */
    protected function encodeIp(string $ip): string
    {
        // Replace / with %2F for CIDR notation
        return str_replace('/', '%2F', $ip);
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
                    'encoded_ip' => $encodedIp,
                    'encoded_ip_on_game' => $encodedIpOnGame,
                    'endpoint' => $endpoint,
                ]);
            }

            // Get list of rule IDs
            $ruleIds = $client->get($endpoint);

            if (!is_array($ruleIds)) {
                throw OvhApiException::invalidResponse('Expected array of rule IDs');
            }

            // Fetch details for each rule
            $rules = [];
            foreach ($ruleIds as $ruleId) {
                try {
                    $rule = $client->get("{$endpoint}/{$ruleId}");
                    $rules[] = array_merge(['id' => $ruleId], $rule);
                } catch (\Exception $e) {
                    Log::warning('Failed to fetch rule details', [
                        'rule_id' => $ruleId,
                        'error' => $e->getMessage(),
                    ]);
                }
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

            $protocol = $protocol ?? $this->settings->default_protocol;
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

            if ($this->shouldLogDetailed()) {
                Log::info('Successfully created firewall rule', [
                    'ip' => $ip,
                    'ip_on_game' => $ipOnGame,
                    'port' => $port,
                    'rule_id' => $result['id'] ?? 'unknown',
                ]);
            }

            $this->invalidateRulesCache($ip, $ipOnGame);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to create firewall rule', [
                'ip' => $ip,
                'ip_on_game' => $ipOnGame,
                'port' => $port,
                'error' => $e->getMessage(),
            ]);

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

            if ($this->shouldLogDetailed()) {
                Log::info('Successfully deleted firewall rule', [
                    'ip' => $ip,
                    'ip_on_game' => $ipOnGame,
                    'rule_id' => $ruleId,
                ]);
            }

            $this->invalidateRulesCache($ip, $ipOnGame);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete firewall rule', [
                'ip' => $ip,
                'ip_on_game' => $ipOnGame,
                'rule_id' => $ruleId,
                'error' => $e->getMessage(),
            ]);

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

            return array_merge(['id' => $ruleId], $rule);
        } catch (\Exception $e) {
            Log::error('Failed to get firewall rule details', [
                'ip' => $ip,
                'ip_on_game' => $ipOnGame,
                'rule_id' => $ruleId,
                'error' => $e->getMessage(),
            ]);

            throw OvhApiException::requestFailed("/ip/{$ip}/game/{$ipOnGame}/rule/{$ruleId}", $e->getMessage());
        }
    }

    /**
     * Find a rule by port.
     */
    public function findRuleByPort(string $ip, string $ipOnGame, int $port): ?array
    {
        try {
            $rules = $this->getRules($ip, $ipOnGame);

            foreach ($rules as $rule) {
                if (isset($rule['ports'])) {
                    $rulePort = PortRangeHelper::getSinglePort($rule['ports']);
                    if ($rulePort === $port) {
                        return $rule;
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to find rule by port', [
                'ip' => $ip,
                'ip_on_game' => $ipOnGame,
                'port' => $port,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if a rule exists for a specific port.
     */
    public function ruleExistsForPort(string $ip, string $ipOnGame, int $port): bool
    {
        return $this->findRuleByPort($ip, $ipOnGame, $port) !== null;
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
