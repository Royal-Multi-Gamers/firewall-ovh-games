<?php

namespace RoyalMultiGamers\FirewallOVHGames\Exceptions;

use Exception;

class OvhApiException extends Exception
{
    /**
     * Create a new OVH API exception instance.
     */
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for connection failure.
     */
    public static function connectionFailed(string $reason = ''): self
    {
        $message = 'Failed to connect to OVH API';
        if ($reason) {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for authentication failure.
     */
    public static function authenticationFailed(string $reason = ''): self
    {
        $message = 'OVH API authentication failed';
        if ($reason) {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for missing credentials.
     */
    public static function missingCredentials(): self
    {
        return new self('OVH API credentials are not configured. Please configure them in the plugin settings.');
    }

    /**
     * Create an exception for API request failure.
     */
    public static function requestFailed(string $endpoint, string $reason = ''): self
    {
        $message = "OVH API request to '{$endpoint}' failed";
        if ($reason) {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for invalid response.
     */
    public static function invalidResponse(string $reason = ''): self
    {
        $message = 'OVH API returned an invalid response';
        if ($reason) {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for rate limiting.
     */
    public static function rateLimitExceeded(): self
    {
        return new self('OVH API rate limit exceeded. Please try again later.');
    }
}
