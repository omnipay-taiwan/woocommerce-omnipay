<?php

namespace WooCommerceOmnipay\Tests\Unit\Http;

use Omnipay\Common\Http\ClientInterface;
use WooCommerceOmnipay\Http\CurlClient;
use WooCommerceOmnipay\Http\NetworkException;

class CurlClientTest extends MockServerTestCase
{
    public function test_implements_client_interface()
    {
        $this->assertInstanceOf(ClientInterface::class, new CurlClient);
    }

    public function test_get_request()
    {
        $client = new CurlClient;

        $response = $client->request('GET', $this->getBaseUrl().'/get');

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('GET', $body['method']);
    }

    public function test_post_request_with_body()
    {
        $client = new CurlClient;

        $response = $client->request(
            'POST',
            $this->getBaseUrl().'/post',
            ['Content-Type' => 'application/json'],
            json_encode(['test' => 'value'])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('{"test":"value"}', $body['data']);
    }

    public function test_request_with_custom_headers()
    {
        $client = new CurlClient;

        $response = $client->request(
            'GET',
            $this->getBaseUrl().'/headers',
            ['X-Custom-Header' => 'test-value']
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('test-value', $body['headers']['X-Custom-Header']);
    }

    public function test_handles_404_response()
    {
        $client = new CurlClient;

        $response = $client->request('GET', $this->getBaseUrl().'/status/404');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_handles_500_response()
    {
        $client = new CurlClient;

        $response = $client->request('GET', $this->getBaseUrl().'/status/500');

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function test_throws_network_exception_on_connection_failure()
    {
        $client = new CurlClient(1);

        $this->expectException(NetworkException::class);

        // 使用不可連接的 IP
        $client->request('GET', 'http://10.255.255.1');
    }

    public function test_timeout()
    {
        $client = new CurlClient(1); // 1 秒 timeout

        $this->expectException(NetworkException::class);

        // 這個請求會延遲 5 秒
        $client->request('GET', $this->getBaseUrl().'/delay/5');
    }
}
