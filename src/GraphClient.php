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
    private string $accessToken;

    /**
     * @param string $accessToken Microsoft Graph access token
     */
    public function __construct(string $accessToken)
    {
        $this->accessToken = $accessToken;
        
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
        return new RequestBuilder($this->httpClient, $this->accessToken, $method, $endpoint);
    }
}

/**
 * Request builder compatible with old Graph SDK API
 */
class RequestBuilder
{
    private Client $httpClient;
    private string $accessToken;
    private string $method;
    private string $endpoint;
    private array $headers = [];
    private $body = null;

    public function __construct(
        Client $httpClient,
        string $accessToken,
        string $method,
        string $endpoint
    ) {
        $this->httpClient = $httpClient;
        $this->accessToken = $accessToken;
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
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept' => 'application/json',
        ], $this->headers);

        $options = [
            'headers' => $headers,
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

        $response = $this->httpClient->request(
            $this->method,
            $this->endpoint,
            $options
        );

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
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->accessToken,
        ], $this->headers);

        $options = [
            'headers' => $headers,
            'stream' => true,
        ];

        $response = $this->httpClient->request(
            $this->method,
            $this->endpoint,
            $options
        );

        return $response->getBody();
    }
}
