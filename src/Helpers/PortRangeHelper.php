<?php

namespace RoyalMultiGamers\FirewallOVHGames\Helpers;

class PortRangeHelper
{
    /**
     * Format a port as a range array for OVH API.
     * For single ports, returns array with from and to set to the same port.
     */
    public static function formatPortRange(int $port): array
    {
        return [
            'from' => $port,
            'to' => $port,
        ];
    }

    /**
     * Parse a port range from OVH API.
     * Handles both string format ("port:port") and array format.
     * Returns an array with 'start' and 'end' keys.
     */
    public static function parsePortRange(string|array $range): array
    {
        // Handle array format from OVH API
        if (is_array($range)) {
            if (isset($range['from']) && isset($range['to'])) {
                return [
                    'start' => (int) $range['from'],
                    'end' => (int) $range['to'],
                ];
            }
            throw new \InvalidArgumentException("Invalid port range array format");
        }

        // Handle string format
        $parts = explode(':', $range);
        
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Invalid port range format: {$range}");
        }

        return [
            'start' => (int) $parts[0],
            'end' => (int) $parts[1],
        ];
    }

    /**
     * Check if a port is within a range.
     */
    public static function isPortInRange(int $port, string|array $range): bool
    {
        $parsed = self::parsePortRange($range);
        
        return $port >= $parsed['start'] && $port <= $parsed['end'];
    }

    /**
     * Check if a port range represents a single port.
     */
    public static function isSinglePort(string|array $range): bool
    {
        $parsed = self::parsePortRange($range);
        
        return $parsed['start'] === $parsed['end'];
    }

    /**
     * Get the single port from a range if it represents a single port.
     */
    public static function getSinglePort(string|array $range): ?int
    {
        if (!self::isSinglePort($range)) {
            return null;
        }

        $parsed = self::parsePortRange($range);
        
        return $parsed['start'];
    }

    /**
     * Validate a port number.
     */
    public static function isValidPort(int $port): bool
    {
        return $port >= 1 && $port <= 65535;
    }

    /**
     * Format port for display.
     */
    public static function formatPortForDisplay(string|array $range): string
    {
        if (self::isSinglePort($range)) {
            return (string) self::getSinglePort($range);
        }

        $parsed = self::parsePortRange($range);
        
        return "{$parsed['start']}-{$parsed['end']}";
    }

    /**
     * Normalize port range to string format.
     * Converts array format to string format.
     */
    public static function normalizePortRange(string|array $range): string
    {
        if (is_string($range)) {
            return $range;
        }

        $parsed = self::parsePortRange($range);
        return "{$parsed['start']}:{$parsed['end']}";
    }
}
