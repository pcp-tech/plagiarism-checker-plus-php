<?php

declare(strict_types=1);

namespace PlagiarismCheckerPlus;

/**
 * A non-2xx response. getErrorCode() is the API's code, such as insufficient_words.
 * getDetails() returns the response's error object (wordsNeeded, billingUrl and other detail fields).
 */
class ApiException extends \RuntimeException
{
    /** @deprecated No longer returned: free accounts get a free API trial and free test keys. */
    public const PLAN_REQUIRED = 'plan_required';
    public const TEXT_TOO_SHORT = 'text_too_short';
    public const DOC_TOO_LONG = 'doc_too_long';
    /** 402: not enough words across all sources (wordsNeeded, wordsAvailable, balances, billingUrl). */
    public const INSUFFICIENT_WORDS = 'insufficient_words';
    /** 402: the free API trial cannot cover the request (wordsNeeded, trialRemaining, trialTotal, billingUrl). */
    public const TRIAL_EXHAUSTED = 'trial_exhausted';
    /** 402: the key's monthly word cap would be exceeded (wordsNeeded, monthlyWordCap, usedThisMonth). */
    public const KEY_CAP_REACHED = 'key_cap_reached';
    public const INVALID_REQUEST = 'invalid_request';
    public const INVALID_JSON = 'invalid_json';
    public const UNAUTHORIZED = 'unauthorized';
    public const INVALID_API_KEY = 'invalid_api_key';
    public const KEY_REVOKED = 'key_revoked';
    public const EMAIL_UNVERIFIED = 'email_unverified';
    public const FORBIDDEN = 'forbidden';
    public const NOT_FOUND = 'not_found';
    public const RATE_LIMITED = 'rate_limited';
    public const WORKERS_UNAVAILABLE = 'workers_unavailable';
    public const INTERNAL_ERROR = 'internal_error';

    public function __construct(
        string $message,
        private int $status,
        private ?string $errorCode,
        private ?float $retryAfter,
        private mixed $body
    ) {
        parent::__construct($message, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** Seconds from Retry-After, if sent. */
    public function getRetryAfter(): ?float
    {
        return $this->retryAfter;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * The response's error object: code, message and any detail fields.
     *
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return is_array($this->body) && is_array($this->body['error'] ?? null) ? $this->body['error'] : [];
    }

    public static function parseRetryAfter(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return max(0.0, (float) $value);
        }
        $time = strtotime($value);
        return $time === false ? null : max(0.0, (float) ($time - time()));
    }
}
