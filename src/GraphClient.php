<?php

namespace KalnaLab\FlysystemMicrosoftGraph;

use GuzzleHttp\Client;

/**
 * GraphClient wrapper that provides Graph SDK v1.x compatible API
 * This allows existing MicrosystemGraphAdapter code to work without changes
 */
class GraphClient
{
    private Client $httpClient;
    private TokenManager $tokenManager;

    /**
     * The access token is resolved lazily per request through the token
     * manager rather than captured here, so a long-lived process (e.g. a
     * queue worker) never sends a token that expired after the client was
     * built.
     *
     * @param TokenManager $tokenManager Resolves and refreshes the Graph access token on demand
     */
    public function __construct(TokenManager $tokenManager)
    {
        $this->tokenManager = $tokenManager;

        $this->httpClient = new Client([
            'base_uri' => 'https://graph.microsoft.com/v1.0/',
            'timeout' => 300,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Create a request compatible with old Graph SDK API
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, PATCH)
     * @param string $endpoint API endpoint (e.g., '/drives/{id}/items/{item-id}')
     * @return RequestBuilder
     */
    public function createRequest(string $method, string $endpoint): RequestBuilder
    {
        return new RequestBuilder($this->httpClient, $this->tokenManager, $method, $endpoint);
    }
}
