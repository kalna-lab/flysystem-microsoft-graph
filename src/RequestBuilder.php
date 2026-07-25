<?php

namespace KalnaLab\FlysystemMicrosoftGraph;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Request builder compatible with old Graph SDK API
 */
class RequestBuilder
{
    private Client $httpClient;
    private TokenManager $tokenManager;
    private string $method;
    private string $endpoint;
    private array $headers = [];
    private $body = null;

    public function __construct(
        Client $httpClient,
        TokenManager $tokenManager,
        string $method,
        string $endpoint
    )
    {
        $this->httpClient = $httpClient;
        $this->tokenManager = $tokenManager;
        $this->method = $method;
        $this->endpoint = ltrim($endpoint, '/');
    }

    /**
     * Add request headers
     */
    public function addHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Attach body to request
     */
    public function attachBody($body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Set request return type
     */
    public function setReturnType($type): self
    {
        // Not used in our adapter, but kept for API compatibility
        return $this;
    }

    /**
     * Execute the request
     */
    public function execute()
    {
        $response = $this->sendWithAuthRetry(function (string $token) {
            $options = [
                'headers' => array_merge([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ], $this->headers),
            ];

            if ($this->body !== null) {
                if (is_string($this->body)) {
                    $options['body'] = $this->body;
                } elseif (is_resource($this->body)) {
                    $options['body'] = $this->body;
                } else {
                    $options['json'] = $this->body;
                }
            }

            return $this->httpClient->request($this->method, $this->endpoint, $options);
        });

        // Parse response
        $contentType = $response->getHeaderLine('Content-Type');

        if (strpos($contentType, 'application/json') !== false) {
            return json_decode($response->getBody()->getContents(), true);
        }

        // Return stream for binary content
        return $response->getBody();
    }

    /**
     * Get stream response
     */
    public function getStream()
    {
        $response = $this->sendWithAuthRetry(function (string $token) {
            $options = [
                'headers' => array_merge([
                    'Authorization' => 'Bearer ' . $token,
                ], $this->headers),
                'stream' => true,
            ];

            return $this->httpClient->request($this->method, $this->endpoint, $options);
        });

        return $response->getBody();
    }

    /**
     * Send a request with a freshly resolved bearer token, retrying exactly
     * once on a 401. The token manager hands out a cached token that is
     * normally still valid; if Graph nonetheless rejects it as expired (the
     * token lapsed between resolution and use, or the cached value was stale),
     * we force-refresh and retry so the caller self-heals instead of failing.
     *
     * The retry is skipped for resource (stream) bodies, which cannot be
     * safely re-sent once partially consumed — the lazy token resolution
     * above already makes a stale token on those requests highly unlikely.
     *
     * @param callable(string): \Psr\Http\Message\ResponseInterface $send
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function sendWithAuthRetry(callable $send)
    {
        try {
            return $send($this->tokenManager->getAccessToken());
        } catch (ClientException $e) {
            $isUnauthorized = $e->getResponse() !== null
                && $e->getResponse()->getStatusCode() === 401;

            if ($isUnauthorized && !is_resource($this->body)) {
                return $send($this->tokenManager->refreshAccessToken());
            }

            throw $e;
        }
    }
}
