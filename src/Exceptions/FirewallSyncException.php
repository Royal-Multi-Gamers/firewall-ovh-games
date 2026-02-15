<?php

namespace RoyalMultiGamers\FirewallOVHGames\Exceptions;

use Exception;

class FirewallSyncException extends Exception
{
    /**
     * Create a new firewall sync exception instance.
     */
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for missing IP configuration.
     */
    public static function missingIpConfiguration(string $ip): self
    {
        return new self("No OVH firewall configuration found for IP: {$ip}. Please configure it in the admin panel.");
    }

    /**
     * Create an exception for disabled IP configuration.
     */
    public static function ipConfigurationDisabled(string $ip): self
    {
        return new self("OVH firewall configuration for IP {$ip} is disabled.");
    }

    /**
     * Create an exception for sync failure.
     */
    public static function syncFailed(string $reason = ''): self
    {
        $message = 'Firewall synchronization failed';
        if ($reason) {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for rule creation failure.
     */
    public static function ruleCreationFailed(string $ip, int $port, string $reason = ''): self
    {
        $message = "Failed to create firewall rule for {$ip}:{$port}";
        if ($reason) {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for rule deletion failure.
     */
    public static function ruleDeletionFailed(string $ip, int $ruleId, string $reason = ''): self
    {
        $message = "Failed to delete firewall rule {$ruleId} for {$ip}";
        if ($reason) {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for rule update failure.
     */
    public static function ruleUpdateFailed(string $ip, int $port, string $reason = ''): self
    {
        $message = "Failed to update firewall rule for {$ip}:{$port}";
        if ($reason) {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for allocation not found.
     */
    public static function allocationNotFound(int $allocationId): self
    {
        return new self("Allocation with ID {$allocationId} not found.");
    }
}
