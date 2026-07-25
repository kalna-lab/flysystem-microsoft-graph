<?php

namespace KalnaLab\FlysystemMicrosoftGraph;

use GuzzleHttp\Client;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class TokenManager
{
    private Client $httpClient;
    private CacheRepository $cache;
    private string $clientId;
    private string $clientSecret;
    private string $tenantId;
    
    /**
     * @param CacheRepository|null $cache Laravel cache instance (null = auto-resolve)
     * @param string|null $clientId Azure AD application client ID (null = from config)
     * @param string|null $clientSecret Azure AD application client secret (null = from config)
     * @param string|null $tenantId Azure AD tenant ID (null = from config)
     */
    public function __construct(
        ?CacheRepository $cache = null,
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $tenantId = null
    ) {
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
        
        // Auto-resolve cache from container if not provided
        $this->cache = $cache ?? app('cache.store');
        
        // Auto-resolve credentials from config if not provided
        $this->clientId = $clientId ?? config('filesystems.disks.sharepoint.clientId')
            ?? config('flysystem-msgraph.defaults.client_id')
            ?? throw new \InvalidArgumentException('Microsoft Graph Client ID not configured');
            
        $this->clientSecret = $clientSecret ?? config('filesystems.disks.sharepoint.clientSecret')
            ?? config('flysystem-msgraph.defaults.client_secret')
            ?? throw new \InvalidArgumentException('Microsoft Graph Client Secret not configured');
            
        $this->tenantId = $tenantId ?? config('filesystems.disks.sharepoint.tenantId')
            ?? config('flysystem-msgraph.defaults.tenant_id')
            ?? throw new \InvalidArgumentException('Microsoft Graph Tenant ID not configured');
    }

    /**
     * Seconds shaved off the token's real lifetime before it is considered
     * expired, so a token is never served (or used mid-request) right on the
     * edge of Azure's own expiry.
     */
    private const EXPIRY_BUFFER_SECONDS = 300;

    /**
     * Get a valid access token (from cache or by requesting a new one).
     *
     * The cache TTL is derived from the token's real `expires_in` rather than
     * a fixed window, so a shorter-lived token issued by Azure is refreshed in
     * time instead of being cached past its actual expiry.
     */
    public function getAccessToken(): string
    {
        $cacheKey = $this->getCacheKey();

        $cached = $this->cache->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        [$token, $expiresIn] = $this->requestAccessToken();
        $ttl = max(60, $expiresIn - self::EXPIRY_BUFFER_SECONDS);
        $this->cache->put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * Force refresh the access token
     */
    public function refreshAccessToken(): string
    {
        $cacheKey = $this->getCacheKey();
        $this->cache->forget($cacheKey);
        
        return $this->getAccessToken();
    }

    /**
     * Request a new access token from Microsoft.
     *
     * @return array{0: string, 1: int} The access token and its lifetime in
     *                                   seconds (`expires_in`).
     */
    private function requestAccessToken(): array
    {
        try {
            $response = $this->httpClient->post(
                "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
                [
                    'form_params' => [
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'scope' => 'https://graph.microsoft.com/.default',
                        'grant_type' => 'client_credentials',
                    ],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['access_token'])) {
                throw new \RuntimeException('No access token in response');
            }

            // Azure always returns expires_in (seconds); fall back to a
            // conservative ~59 min only if a future/altered response omits it.
            $expiresIn = (int) ($data['expires_in'] ?? 3540);

            return [$data['access_token'], $expiresIn];
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to obtain Microsoft Graph access token: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Generate a unique cache key based on credentials
     */
    private function getCacheKey(): string
    {
        return 'msgraph_token_' . md5($this->clientId . $this->clientSecret . $this->tenantId);
    }

    /**
     * Clear the cached token
     */
    public function clearToken(): void
    {
        $this->cache->forget($this->getCacheKey());
    }
}
