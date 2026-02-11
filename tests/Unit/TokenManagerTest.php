<?php

namespace KalnaLab\FlysystemMicrosoftGraph\Tests\Unit;

use KalnaLab\FlysystemMicrosoftGraph\TokenManager;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\TestCase;

class TokenManagerTest extends TestCase
{
    private Repository $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new Repository(new ArrayStore());
    }

    public function test_it_generates_unique_cache_keys()
    {
        $manager1 = new TokenManager(
            $this->cache,
            'client1',
            'secret1',
            'tenant1'
        );

        $manager2 = new TokenManager(
            $this->cache,
            'client2',
            'secret2',
            'tenant2'
        );

        // Use reflection to access private method
        $reflection = new \ReflectionClass($manager1);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);

        $key1 = $method->invoke($manager1);
        $key2 = $method->invoke($manager2);

        $this->assertNotEquals($key1, $key2);
        $this->assertStringStartsWith('msgraph_token_', $key1);
        $this->assertStringStartsWith('msgraph_token_', $key2);
    }

    public function test_it_can_clear_token()
    {
        $manager = new TokenManager(
            $this->cache,
            'client-id',
            'client-secret',
            'tenant-id'
        );

        // Put something in cache
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);
        $cacheKey = $method->invoke($manager);

        $this->cache->put($cacheKey, 'test-token', 60);
        $this->assertTrue($this->cache->has($cacheKey));

        // Clear token
        $manager->clearToken();
        $this->assertFalse($this->cache->has($cacheKey));
    }
}
