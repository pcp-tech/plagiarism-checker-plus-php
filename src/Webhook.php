<?php

declare(strict_types=1);

namespace PlagiarismCheckerPlus;

/**
 * Verifies webhook deliveries.
 *
 * Every delivery carries
 *   PCP-Signature: t=<unix seconds>,v1=<hex HMAC-SHA256(secret, "<t>.<raw body>")>
 */
final class Webhook
{
    public const SIGNATURE_HEADER = 'PCP-Signature';
    /** How old a signed timestamp may be, in seconds, before it is rejected. */
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Check a PCP-Signature header against the raw request body
     * (file_get_contents('php://input'), before json_decode).
     *
     * Returns false for a missing or malformed header, a wrong signature, or a
     * timestamp more than $tolerance seconds from $now (default: the current time).
     * Signatures are compared in constant time.
     */
    public static function verifySignature(
        string $secret,
        ?string $header,
        string $body,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null
    ): bool {
        if ($secret === '' || $header === null || $header === '') {
            return false;
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) !== 2) {
                continue;
            }
            $key = trim($pair[0]);
            $value = trim($pair[1]);
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '' && ctype_xdigit($value)) {
                $signatures[] = strtolower($value);
            }
        }
        if ($timestamp === null || $signatures === []) {
            return false;
        }
        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }
}
