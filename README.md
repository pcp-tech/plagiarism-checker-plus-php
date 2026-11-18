# Plagiarism Checker Plus for PHP

Official client for the [Plagiarism Checker Plus API](https://plagiarismcheckerplus.com/integrations/api). PHP 8.1+, no dependencies.

The API needs a paid plan. A key on the free plan returns `403 plan_required`.

```sh
composer require plagiarismcheckerplus/sdk
```

```php
use PlagiarismCheckerPlus\ApiException;
use PlagiarismCheckerPlus\Client;

$client = new Client(getenv('PCP_API_KEY'));

try {
    $created = $client->createScan($essayText, ['plagiarism', 'ai_detect'], 'essay-draft-1');
    $scan = $client->waitForScan($created['id']);

    echo 'Matched: ', $scan['results']['plagiarism']['score'] ?? '-', "%\n";
    foreach ($scan['results']['plagiarism']['sources'] ?? [] as $source) {
        echo $source['url'], "\n";
    }

    // A likelihood, not proof that the text was written by AI.
    echo 'AI likelihood: ', $scan['results']['aiDetection']['score'] ?? '-', "%\n";
} catch (ApiException $e) {
    if ($e->getErrorCode() === ApiException::TEXT_TOO_SHORT) {
        echo "Send at least 30 words for plagiarism, 50 for AI detection.\n";
    } else {
        throw $e;
    }
}
```

## Methods

| Method | API call |
|---|---|
| `createScan($text, $tools, $name = null)` | `POST /scans` |
| `getScan($id, $includeText = false)` | `GET /scans/{id}` |
| `listScans($page = null, $limit = null)` | `GET /scans` |
| `getAccount()` | `GET /account` |
| `waitForScan($id, $timeout = 300, $interval = 2, $maxInterval = 10)` | Polls until `completed` or `failed` |

`waitForScan` grows the delay by 1.5× up to `$maxInterval`, waits for `Retry-After` on `429`, and throws `ScanTimeoutException` when `$timeout` passes.

Failed calls throw `ApiException` with `getStatus()`, `getErrorCode()` (such as `plan_required`, `text_too_short`, `doc_too_long`, `insufficient_words`) and `getRetryAfter()`. Pass your own `Transport` to the constructor for proxies or tests.

Run the tests with `composer test`.

## Error handling

Failed calls raise `PlagiarismCheckerPlus\ApiException`. It carries the HTTP status and the API error code:

| Code | Status | Meaning |
|---|---|---|
| `plan_required` | 403 | The key's plan has no API access. The API needs a paid plan. |
| `text_too_short` | 400 | Plagiarism needs at least 30 words, AI detection at least 50. Nothing is charged. |
| `doc_too_long` | 400 | The text is longer than the plan allows per check. |
| `insufficient_words` | 402 | Not enough words left on the account. |

Other codes (for example on `401`, `404`, `429` or `503`) come through as the raw string the API sends. On `429` the client honours `Retry-After`. `waitForScan` raises `PlagiarismCheckerPlus\ScanTimeoutException` when it runs out of time. A scan whose status is `failed` is returned, not raised, so check `status`.

## Notes

- **An AI score is a likelihood, not proof** that someone used AI. A similarity score does not decide on its own whether text is plagiarism. Review the result yourself, and do not use either score alone to accuse anyone.
- **The API needs a paid plan.** A key on the free plan returns `403 plan_required`.
- Each tool you run is billed at the full word count. The rate limit is 60 requests a minute per key.
- Plagiarism Checker API overview: https://plagiarismcheckerplus.com/integrations/api
- AI Detector API overview: https://plagiarismcheckerplus.com/ai-detector-api
- API reference (try requests in the browser): https://app.plagiarismcheckerplus.com/api-docs
- Create an API key: https://app.plagiarismcheckerplus.com/settings/api
- The OpenAPI spec this client follows is in [`spec/openapi.json`](spec/openapi.json).

## Other languages

| Language | Repository | Install |
|---|---|---|
| JavaScript / TypeScript | [plagiarism-checker-plus-js](https://github.com/pcp-tech/plagiarism-checker-plus-js) | `npm install plagiarism-checker-plus` |
| Python | [plagiarism-checker-plus-python](https://github.com/pcp-tech/plagiarism-checker-plus-python) | `pip install plagiarism-checker-plus` |
| **PHP** (this repository) | [plagiarism-checker-plus-php](https://github.com/pcp-tech/plagiarism-checker-plus-php) | `composer require plagiarismcheckerplus/sdk` |
| Ruby | [plagiarism-checker-plus-ruby](https://github.com/pcp-tech/plagiarism-checker-plus-ruby) | `gem install plagiarism_checker_plus` |
| Go | [plagiarism-checker-plus-go](https://github.com/pcp-tech/plagiarism-checker-plus-go) | `go get github.com/pcp-tech/plagiarism-checker-plus-go` |
| Java | [plagiarism-checker-plus-java](https://github.com/pcp-tech/plagiarism-checker-plus-java) | Maven `com.plagiarismcheckerplus:sdk` |
| C# / .NET | [plagiarism-checker-plus-dotnet](https://github.com/pcp-tech/plagiarism-checker-plus-dotnet) | `dotnet add package PlagiarismCheckerPlus` |

## License

MIT. Copyright (c) 2026 Plagiarism Checker Plus.
