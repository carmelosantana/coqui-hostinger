<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitHostinger\Runtime\HostingerClient;
use CarmeloSantana\CoquiToolkitHostinger\Runtime\HostingerResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

test('get request sends correct authorization header', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url, 'options' => $options];
        return new MockResponse(json_encode(['id' => 1, 'hostname' => 'test.server.com']), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $client = new HostingerClient(apiToken: 'test-token-123', httpClient: $mockClient);
    $result = $client->get('virtual-machines');

    expect($result->success)->toBeTrue();
    expect($captured['method'])->toBe('GET');
    expect($captured['url'])->toContain('developers.hostinger.com/api/vps/v1/virtual-machines');

    $authHeader = '';
    foreach ($captured['options']['headers'] ?? [] as $header) {
        if (str_starts_with($header, 'Authorization:')) {
            $authHeader = $header;
        }
    }
    expect($authHeader)->toContain('Bearer test-token-123');
});

test('post request sends JSON body', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url, 'options' => $options];
        return new MockResponse(json_encode(['id' => 42, 'name' => 'new-firewall']), [
            'http_code' => 201,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $client = new HostingerClient(apiToken: 'test-token', httpClient: $mockClient);
    $result = $client->post('firewall', ['name' => 'web-fw']);

    expect($result->success)->toBeTrue();
    expect($captured['method'])->toBe('POST');
    $body = $captured['options']['body'] ?? '';
    expect($body)->toContain('web-fw');
});

test('delete request uses correct method', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url];
        return new MockResponse('', [
            'http_code' => 204,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $client = new HostingerClient(apiToken: 'test-token', httpClient: $mockClient);
    $result = $client->delete('firewall/42');

    expect($result->success)->toBeTrue();
    expect($captured['method'])->toBe('DELETE');
});

test('missing token returns error result', function () {
    $mockClient = new MockHttpClient([]);
    $client = new HostingerClient(apiToken: '', httpClient: $mockClient);

    $result = $client->get('virtual-machines');

    expect($result->success)->toBeFalse();
    expect($result->errorMessage())->toContain('HOSTINGER_API_TOKEN');
});

test('http error response returns structured error', function () {
    $mockClient = new MockHttpClient([
        new MockResponse(json_encode(['message' => 'VM not found', 'errors' => [['message' => 'Resource not found']]]), [
            'http_code' => 404,
            'response_headers' => ['content-type' => 'application/json'],
        ]),
    ]);

    $client = new HostingerClient(apiToken: 'test-token', httpClient: $mockClient);
    $result = $client->get('virtual-machines/999999');

    expect($result->success)->toBeFalse();
    expect($result->statusCode)->toBe(404);
});

test('paginate merges results from multiple pages', function () {
    $page = 0;
    $mockClient = new MockHttpClient(function () use (&$page): MockResponse {
        $page++;
        if ($page === 1) {
            return new MockResponse(json_encode([
                ['id' => 1, 'name' => 'vm-1'],
                ['id' => 2, 'name' => 'vm-2'],
            ]), [
                'http_code' => 200,
                'response_headers' => ['content-type' => 'application/json'],
            ]);
        }

        // Return empty array to signal end of pagination
        return new MockResponse(json_encode([]), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $client = new HostingerClient(apiToken: 'test-token', httpClient: $mockClient);
    $result = $client->paginate('virtual-machines');

    expect($result->success)->toBeTrue();
    expect($result->data)->toHaveCount(2);
});

test('fromEnv creates client without errors', function () {
    $client = HostingerClient::fromEnv();

    expect($client)->toBeInstanceOf(HostingerClient::class);
});

test('put request sends JSON body', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'options' => $options];
        return new MockResponse(json_encode(['success' => true]), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $client = new HostingerClient(apiToken: 'test-token', httpClient: $mockClient);
    $result = $client->put('virtual-machines/1/hostname', ['hostname' => 'new.host.com']);

    expect($result->success)->toBeTrue();
    expect($captured['method'])->toBe('PUT');
    $body = $captured['options']['body'] ?? '';
    expect($body)->toContain('new.host.com');
});
