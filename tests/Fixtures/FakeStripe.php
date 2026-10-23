<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Tests\Fixtures;

use Stripe\HttpClient\ClientInterface;

/**
 * Recorded Stripe API responses, served in place of the network. Routes are "METHOD /v1/path"; the first route
 * whose path is a prefix of the request path wins, so "GET /v1/subscriptions/sub_1" can be registered on its own.
 * Every request is recorded, and a request with no route fails the test with a 404 from "Stripe".
 */
class FakeStripe implements ClientInterface
{
    /** @var array<string, array{0: int, 1: array<string, mixed>}> */
    public static array $routes = [];

    /** @var array<int, array{method: string, path: string, params: array<string, mixed>}> */
    public static array $requests = [];

    public static function reset(): void
    {
        static::$routes = [];
        static::$requests = [];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function route(string $route, array $body, int $status = 200): void
    {
        static::$routes[$route] = [$status, $body];
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    public static function list(string $url, array $data): array
    {
        return ['object' => 'list', 'url' => $url, 'has_more' => false, 'data' => $data];
    }

    public static function count(string $route): int
    {
        [$method, $path] = explode(' ', $route, 2);

        return count(array_filter(static::$requests, fn (array $r): bool => $r['method'] === $method && $r['path'] === $path));
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = (string) parse_url($absUrl, PHP_URL_PATH);
        $method = strtoupper($method);
        static::$requests[] = ['method' => $method, 'path' => $path, 'params' => (array) $params];

        foreach (static::$routes as $route => [$status, $body]) {
            [$routeMethod, $routePath] = explode(' ', $route, 2);

            if ($routeMethod === $method && $routePath === $path) {
                return [json_encode($body), $status, ['request-id' => 'req_fake']];
            }
        }

        return [json_encode(['error' => ['type' => 'invalid_request_error', 'message' => "No fake route for {$method} {$path}"]]), 404, []];
    }
}
