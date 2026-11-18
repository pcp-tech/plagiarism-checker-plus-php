<?php

declare(strict_types=1);

// Dependency-free tests: php tests/run.php (or composer test).

spl_autoload_register(static function (string $class): void {
    $prefix = 'PlagiarismCheckerPlus\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__ . '/../src/' . substr($class, strlen($prefix)) . '.php';
    }
});

use PlagiarismCheckerPlus\ApiException;
use PlagiarismCheckerPlus\Client;
use PlagiarismCheckerPlus\Response;
use PlagiarismCheckerPlus\ScanTimeoutException;
use PlagiarismCheckerPlus\Transport;
use PlagiarismCheckerPlus\Webhook;

final class FakeTransport implements Transport
{
    /** @var list<array{string,string,array<string,string>,?string}> */
    public array $calls = [];

    /** @param list<Response> $responses */
    public function __construct(private array $responses)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body): Response
    {
        $this->calls[] = [$method, $url, $headers, $body];
        return array_shift($this->responses);
    }
}

function json(int $status, mixed $body, array $headers = []): Response
{
    return new Response($status, $headers, json_encode($body));
}

$failures = 0;
$tests = 0;
function check(bool $ok, string $label): void
{
    global $failures, $tests;
    $tests++;
    if (!$ok) {
        $failures++;
        echo "FAIL: $label\n";
    }
}

// createScan sends key and body.
$t = new FakeTransport([json(202, ['id' => 's1', 'status' => 'processing'])]);
$client = new Client('pcp_test', Client::DEFAULT_BASE_URL, $t);
check($client->createScan('text', ['plagiarism'], 'draft')['id'] === 's1', 'createScan returns id');
check($t->calls[0][0] === 'POST', 'createScan uses POST');
check($t->calls[0][1] === 'https://app.plagiarismcheckerplus.com/api/public/v1/scans', 'createScan url');
check($t->calls[0][2]['Authorization'] === 'Bearer pcp_test', 'bearer header');
check(json_decode($t->calls[0][3], true) === ['text' => 'text', 'tools' => ['plagiarism'], 'name' => 'draft'], 'createScan body');

// Query parameters.
$t = new FakeTransport([json(200, ['id' => 's1']), json(200, ['data' => []])]);
$client = new Client('k', Client::DEFAULT_BASE_URL, $t);
$client->getScan('s1', true);
$client->listScans(2, 5);
check(str_ends_with($t->calls[0][1], '/scans/s1?include_text=true'), 'getScan query');
check(str_ends_with($t->calls[1][1], '/scans?page=2&limit=5'), 'listScans query');

// Error code and details.
$billing = 'https://app.plagiarismcheckerplus.com/developers/billing';
$t = new FakeTransport([json(402, ['error' => [
    'code' => 'trial_exhausted', 'message' => 'Trial used up', 'wordsNeeded' => 900, 'billingUrl' => $billing,
]])]);
$client = new Client('k', Client::DEFAULT_BASE_URL, $t);
try {
    $client->createScan('text', ['plagiarism']);
    check(false, 'error thrown');
} catch (ApiException $e) {
    check($e->getStatus() === 402, 'error status');
    check($e->getErrorCode() === ApiException::TRIAL_EXHAUSTED, 'error code');
    check(($e->getDetails()['billingUrl'] ?? null) === $billing, 'error details');
}
check(ApiException::KEY_CAP_REACHED === 'key_cap_reached', 'key_cap_reached constant');
check(ApiException::PLAN_REQUIRED === 'plan_required', 'deprecated constant kept');

// Webhook signatures.
$secret = 'whsec_test_secret';
$body = '{"id":"evt_1","type":"scan.completed","createdAt":"2026-11-18T00:00:00Z","data":{}}';
$ts = 1800000000;
$sign = static fn (int $t, string $b, string $k = 'whsec_test_secret'): string => hash_hmac('sha256', $t . '.' . $b, $k);
$header = "t=$ts,v1=" . $sign($ts, $body);
check(Webhook::SIGNATURE_HEADER === 'PCP-Signature', 'webhook header name');
check(Webhook::verifySignature($secret, $header, $body, 300, $ts + 10), 'webhook valid');
check(Webhook::verifySignature($secret, "t=$ts,v1=" . $sign($ts, $body, 'other') . ',v1=' . $sign($ts, $body), $body, 300, $ts), 'webhook rotation');
check(!Webhook::verifySignature('whsec_wrong', $header, $body, 300, $ts), 'webhook wrong secret');
check(!Webhook::verifySignature($secret, $header, $body . ' ', 300, $ts), 'webhook changed body');
check(!Webhook::verifySignature($secret, $header, $body, 300, $ts + 301), 'webhook too old');
check(!Webhook::verifySignature($secret, $header, $body, 300, $ts - 301), 'webhook too new');
check(Webhook::verifySignature($secret, $header, $body, 600, $ts + 500), 'webhook custom tolerance');
check(!Webhook::verifySignature($secret, 'v1=' . $sign($ts, $body), $body, 300, $ts), 'webhook missing t');
check(!Webhook::verifySignature($secret, "t=$ts", $body, 300, $ts), 'webhook missing v1');
check(!Webhook::verifySignature($secret, null, $body, 300, $ts), 'webhook no header');

// waitForScan honours Retry-After.
$t = new FakeTransport([
    json(200, ['id' => 's1', 'status' => 'processing']),
    json(429, ['error' => ['code' => 'rate_limited', 'message' => 'Slow down']], ['Retry-After' => '3']),
    json(200, ['id' => 's1', 'status' => 'completed']),
]);
$waits = [];
$client = new Client('k', Client::DEFAULT_BASE_URL, $t);
$scan = $client->waitForScan('s1', 300.0, 1.0, 10.0, false, static function (float $s) use (&$waits): void {
    $waits[] = $s;
});
check($scan['status'] === 'completed', 'waitForScan completes');
check($waits === [1.0, 3.0], 'waitForScan delays');

// waitForScan times out.
$t = new FakeTransport([json(200, ['id' => 's1', 'status' => 'processing'])]);
$client = new Client('k', Client::DEFAULT_BASE_URL, $t);
try {
    $client->waitForScan('s1', 0.01, 1.0, 10.0, false, static function (float $s): void {
    });
    check(false, 'timeout thrown');
} catch (ScanTimeoutException $e) {
    check(true, 'timeout thrown');
}

echo $failures === 0 ? "OK ($tests checks)\n" : "$failures of $tests checks failed\n";
exit($failures === 0 ? 0 : 1);
