<?php

namespace WooCommerceOmnipay\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;

/**
 * Mock Server Test Case
 *
 * 用途：
 * 為 HTTP Client 測試提供本地 Mock Server，
 * 在測試開始前自動啟動 PHP built-in server，測試結束後自動關閉。
 *
 * 使用方式：
 *   class MyHttpClientTest extends MockServerTestCase
 *   {
 *       public function test_get_request()
 *       {
 *           $client = new MyHttpClient();
 *           $response = $client->request('GET', $this->getBaseUrl() . '/get');
 *           $this->assertEquals(200, $response->getStatusCode());
 *       }
 *   }
 *
 * 優點：
 *   - 不依賴外部服務（如 httpbin.org）
 *   - 測試速度快（本地請求 < 5ms）
 *   - 可控制 response（模擬各種 status code、timeout）
 *   - CI 環境友善（不會因網路問題失敗）
 */
abstract class MockServerTestCase extends TestCase
{
    protected static $serverProcess;

    protected static $serverHost = '127.0.0.1';

    protected static $serverPort = 8765;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::startServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
        parent::tearDownAfterClass();
    }

    protected static function startServer(): void
    {
        $serverScript = __DIR__.'/../../server.php';
        $command = sprintf(
            'php -S %s:%d %s > /dev/null 2>&1 & echo $!',
            self::$serverHost,
            self::$serverPort,
            $serverScript
        );

        $pid = exec($command);
        self::$serverProcess = (int) $pid;

        // 等待 server 啟動
        usleep(100000); // 100ms
    }

    protected static function stopServer(): void
    {
        if (self::$serverProcess) {
            exec('kill '.self::$serverProcess.' 2>/dev/null');
            self::$serverProcess = null;
        }
    }

    protected function getBaseUrl(): string
    {
        return sprintf('http://%s:%d', self::$serverHost, self::$serverPort);
    }
}
