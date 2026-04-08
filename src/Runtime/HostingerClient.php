<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitHostinger\Runtime;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Low-level HTTP client for the Hostinger REST API v1.
 *
 * Handles authentication, request dispatch, response parsing,
 * and automatic pagination. All tool classes delegate to this client.
 */
final class HostingerClient
{
    private const string BASE_URL = 'https://developers.hostinger.com/api/vps/v1';
    private const int TIMEOUT = 30;

    private string $resolvedToken = '';

    public function __construct(
        private readonly string $apiToken = '',
        private readonly HttpClientInterface $httpClient = new \Symfony\Component\HttpClient\CurlHttpClient(),
    ) {}

    /**
     * Create a client from environment variables.
     */
    public static function fromEnv(): self
    {
        return new self(
            apiToken: self::envString('HOSTINGER_API_TOKEN'),
        );
    }

    // -- HTTP verbs ----------------------------------------------------------

    /**
     * @param array<string, mixed> $query
     */
    public function get(string $endpoint, array $query = []): HostingerResult
    {
        return $this->request('GET', $endpoint, query: $query);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function post(string $endpoint, array $body = []): HostingerResult
    {
        return $this->request('POST', $endpoint, body: $body);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function put(string $endpoint, array $body = []): HostingerResult
    {
        return $this->request('PUT', $endpoint, body: $body);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function patch(string $endpoint, array $body = []): HostingerResult
    {
        return $this->request('PATCH', $endpoint, body: $body);
    }

    public function delete(string $endpoint): HostingerResult
    {
        return $this->request('DELETE', $endpoint);
    }

    // -- Pagination ----------------------------------------------------------

    /**
     * Fetch all pages of a paginated endpoint and merge results.
     *
     * @param array<string, mixed> $query
     * @param int $maxPages Safety cap to prevent runaway pagination
     * @return HostingerResult Combined result with merged data array
     */
    public function paginate(string $endpoint, array $query = [], int $maxPages = 10): HostingerResult
    {
        $allData = [];
        $page = 1;

        for ($i = 0; $i < $maxPages; $i++) {
            $query['page'] = $page;
            $result = $this->get($endpoint, $query);

            if (!$result->success) {
                return $result;
            }

            $data = $result->data;
            if (is_array($data)) {
                $allData = array_merge($allData, $data);
            }

            // Hostinger uses standard Laravel-style pagination
            // If we got fewer results than expected or no data, stop
            if (!is_array($data) || count($data) === 0) {
                break;
            }

            $page++;
        }

        return new HostingerResult(
            success: true,
            data: $allData,
            statusCode: 200,
        );
    }

    // -- Internal ------------------------------------------------------------

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    private function request(
        string $method,
        string $endpoint,
        array $query = [],
        array $body = [],
    ): HostingerResult {
        $token = $this->resolveApiToken();
        if ($token === '') {
            return HostingerResult::error(
                'HOSTINGER_API_TOKEN is not configured. '
                . 'Set it via the credentials tool: credentials(action: "set", key: "HOSTINGER_API_TOKEN", value: "your-token")',
            );
        }

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => self::TIMEOUT,
        ];

        if ($query !== []) {
            $options['query'] = $this->filterQuery($query);
        }

        if ($body !== [] && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $options['json'] = $body;
        }

        $url = self::BASE_URL . '/' . ltrim($endpoint, '/');

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();

            return $this->parseResponse($response, $statusCode);
        } catch (HttpExceptionInterface $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            try {
                $json = $e->getResponse()->toArray(false);
                $message = $json['message'] ?? $json['error'] ?? $e->getMessage();
                $errors = is_array($json['errors'] ?? null)
                    ? array_map(
                        static fn(mixed $err): array => is_array($err) ? $err : ['message' => (string) $err],
                        $json['errors'],
                    )
                    : [['message' => (string) $message]];
            } catch (\Throwable) {
                $errors = [['message' => $e->getMessage()]];
            }

            return new HostingerResult(
                success: false,
                data: null,
                errors: $errors,
                statusCode: $statusCode,
            );
        } catch (TransportExceptionInterface $e) {
            return HostingerResult::error('Transport error: ' . $e->getMessage());
        }
    }

    /**
     * Parse the Hostinger API response.
     *
     * Hostinger returns either:
     * - A direct data object/array (for successful responses)
     * - A `{ "data": ... }` envelope (for some endpoints)
     * - An empty body with 2xx status (for some delete/action endpoints)
     */
    private function parseResponse(ResponseInterface $response, int $statusCode): HostingerResult
    {
        $content = $response->getContent(false);

        // Empty body (e.g. 204 No Content or action endpoints)
        if ($content === '' || $content === '[]') {
            return new HostingerResult(
                success: $statusCode >= 200 && $statusCode < 300,
                data: null,
                statusCode: $statusCode,
            );
        }

        $json = json_decode($content, true);

        if (!is_array($json)) {
            return new HostingerResult(
                success: $statusCode >= 200 && $statusCode < 300,
                data: $content,
                statusCode: $statusCode,
            );
        }

        // Check for error response
        if ($statusCode >= 400) {
            $message = $json['message'] ?? $json['error'] ?? 'API error';
            $errors = is_array($json['errors'] ?? null)
                ? array_map(
                    static fn(mixed $err): array => is_array($err) ? $err : ['message' => (string) $err],
                    $json['errors'],
                )
                : [['message' => (string) $message]];

            return new HostingerResult(
                success: false,
                data: null,
                errors: $errors,
                statusCode: $statusCode,
            );
        }

        // If response has a "data" wrapper, unwrap it
        $data = array_key_exists('data', $json) ? $json['data'] : $json;

        return new HostingerResult(
            success: true,
            data: $data,
            statusCode: $statusCode,
        );
    }

    /**
     * Remove null/empty-string values from query parameters.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function filterQuery(array $query): array
    {
        return array_filter($query, static fn(mixed $v): bool => $v !== null && $v !== '');
    }

    /**
     * Lazy-resolve the API token for hot-reload compatibility.
     */
    private function resolveApiToken(): string
    {
        if ($this->resolvedToken !== '') {
            return $this->resolvedToken;
        }

        if ($this->apiToken !== '') {
            $this->resolvedToken = $this->apiToken;
            return $this->resolvedToken;
        }

        $env = getenv('HOSTINGER_API_TOKEN');
        $this->resolvedToken = is_string($env) && $env !== '' ? $env : '';

        return $this->resolvedToken;
    }

    private static function envString(string $name): string
    {
        $value = getenv($name);
        return is_string($value) && $value !== '' ? $value : '';
    }
}
