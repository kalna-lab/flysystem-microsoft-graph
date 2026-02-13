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
     * Get a valid access token (from cache or by requesting a new one)
     */
    public function getAccessToken(): string
    {
        $cacheKey = $this->getCacheKey();

        return $this->cache->remember($cacheKey, 58 * 60, function () {
            return $this->requestAccessToken();
        });
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
     * Request a new access token from Microsoft
     */
    private function requestAccessToken(): string
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

            return $data['access_token'];
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
