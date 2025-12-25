<?php

/**
 * Mock HTTP Server for Testing
 *
 * 用途：
 * 提供本地 HTTP 服務，用於測試 HttpClient 實作，
 * 避免依賴外部服務（如 httpbin.org），確保測試穩定性與速度。
 *
 * 使用方式:
 *   php -S localhost:8080 tests/server.php
 *
 * 支援的端點:
 *   GET  /get          - 回傳 request 資訊（method, headers, url）
 *   POST /post         - 回傳 POST body 與 headers
 *   GET  /headers      - 回傳所有 request headers
 *   GET  /status/404   - 回傳 404 Not Found
 *   GET  /status/500   - 回傳 500 Internal Server Error
 *   GET  /delay/1      - 延遲 1 秒後回應（測試 timeout）
 *   GET  /delay/5      - 延遲 5 秒後回應（測試 timeout）
 */
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// 設定 JSON response header
header('Content-Type: application/json');

// 路由處理
switch ($uri) {
    case '/get':
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
        }
        echo json_encode([
            'url' => "http://{$_SERVER['HTTP_HOST']}{$uri}",
            'method' => $method,
            'headers' => getallheaders(),
        ]);
        break;

    case '/post':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
        }
        echo json_encode([
            'url' => "http://{$_SERVER['HTTP_HOST']}{$uri}",
            'method' => $method,
            'headers' => getallheaders(),
            'data' => file_get_contents('php://input'),
        ]);
        break;

    case '/headers':
        echo json_encode([
            'headers' => getallheaders(),
        ]);
        break;

    case '/status/404':
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
        break;

    case '/status/500':
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error']);
        break;

    case '/delay/1':
        sleep(1);
        echo json_encode(['delayed' => 1]);
        break;

    case '/delay/5':
        sleep(5);
        echo json_encode(['delayed' => 5]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not Found', 'uri' => $uri]);
        break;
}
