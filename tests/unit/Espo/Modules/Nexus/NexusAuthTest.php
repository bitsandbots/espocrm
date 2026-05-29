<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\Nexus;

use Espo\Modules\Nexus\Services\NexusAuth;
use PHPUnit\Framework\TestCase;

class NexusAuthTest extends TestCase
{
    private const BASE_URL  = 'http://nexus.test:5000';
    private const USERNAME  = 'testuser';
    private const PASSWORD  = 'testpass';

    private string $cacheFile;

    protected function setUp(): void
    {
        $this->cacheFile = sys_get_temp_dir()
            . '/nexus_espo_token_'
            . hash('sha256', self::BASE_URL . self::USERNAME)
            . '.json';

        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    public function testGetTokenReturnsCachedToken(): void
    {
        $this->writeCache('cached-token', time() + 3600);

        $auth = new NexusAuth(self::BASE_URL, self::USERNAME, self::PASSWORD);

        $this->assertSame('cached-token', $auth->getToken());
    }

    public function testGetTokenSkipsLoginWhenCacheIsValid(): void
    {
        $this->writeCache('cached-token', time() + 3600);

        $auth = $this->stubAuth('should-not-be-called');

        $this->assertSame('cached-token', $auth->getToken());
    }

    public function testGetTokenFetchesNewTokenWhenNoCacheFile(): void
    {
        $auth = $this->stubAuth('fresh-token');

        $this->assertSame('fresh-token', $auth->getToken());
    }

    public function testGetTokenFetchesNewTokenWhenCacheExpired(): void
    {
        $this->writeCache('old-token', time() - 1);

        $auth = $this->stubAuth('new-token');

        $this->assertSame('new-token', $auth->getToken());
    }

    public function testGetTokenFetchesNewTokenWithinExpiryBuffer(): void
    {
        // 299 seconds remaining — inside the 300 s buffer
        $this->writeCache('almost-expired', time() + 299);

        $auth = $this->stubAuth('refreshed-token');

        $this->assertSame('refreshed-token', $auth->getToken());
    }

    public function testGetTokenFetchesNewTokenWhenCacheIsCorrupt(): void
    {
        file_put_contents($this->cacheFile, 'not-valid-json{{{');

        $auth = $this->stubAuth('fresh-token');

        $this->assertSame('fresh-token', $auth->getToken());
    }

    public function testGetTokenFetchesNewTokenWhenCacheMissingFields(): void
    {
        file_put_contents($this->cacheFile, json_encode(['token' => 'no-expiry']));

        $auth = $this->stubAuth('fresh-token');

        $this->assertSame('fresh-token', $auth->getToken());
    }

    public function testInvalidateRemovesCacheFile(): void
    {
        $this->writeCache('some-token', time() + 3600);

        $auth = new NexusAuth(self::BASE_URL, self::USERNAME, self::PASSWORD);
        $auth->invalidate();

        $this->assertFileDoesNotExist($this->cacheFile);
    }

    public function testInvalidateIsNoopWhenNoCacheFile(): void
    {
        $auth = new NexusAuth(self::BASE_URL, self::USERNAME, self::PASSWORD);
        $auth->invalidate(); // must not throw

        $this->assertFileDoesNotExist($this->cacheFile);
    }

    // ------------------------------------------------------------------

    private function writeCache(string $token, int $expiresAt): void
    {
        file_put_contents(
            $this->cacheFile,
            json_encode(['token' => $token, 'expires_at' => $expiresAt])
        );
    }

    /** Returns a NexusAuth whose login() returns $token without making HTTP calls. */
    private function stubAuth(string $token): NexusAuth
    {
        return new class(self::BASE_URL, self::USERNAME, self::PASSWORD, $token) extends NexusAuth {
            public function __construct(
                string $baseUrl,
                string $username,
                string $password,
                private readonly string $stubToken,
            ) {
                parent::__construct($baseUrl, $username, $password);
            }

            public function login(): string
            {
                return $this->stubToken;
            }
        };
    }
}
