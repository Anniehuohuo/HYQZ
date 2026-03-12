<?php

$baseUrl = getenv('ZHIPU_BASE_URL') ?: 'https://open.bigmodel.cn/api/llm-application/open';
$appId = getenv('ZHIPU_APP_ID') ?: '';
$apiKey = getenv('ZHIPU_API_KEY') ?: '';
$message = $argv[1] ?? '你好';

if ($appId === '' || $apiKey === '') {
    fwrite(STDERR, "用法：\n");
    fwrite(STDERR, "  PowerShell:\n");
    fwrite(STDERR, "    \$env:ZHIPU_APP_ID='你的app_id'\n");
    fwrite(STDERR, "    \$env:ZHIPU_API_KEY='你的api_key'\n");
    fwrite(STDERR, "    php test_zhipu_v2.php \"你好\"\n");
    exit(1);
}

function httpRequest(string $method, string $url, array $headers, string $body = '', bool $stream = false): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    if ($body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $buf = '';
    if ($stream) {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$buf) {
            $buf .= $chunk;
            echo $chunk;
            return strlen($chunk);
        });
    } else {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    }

    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($stream) {
        return [$code, $buf, $err];
    }

    return [$code, is_string($res) ? $res : '', $err];
}

$headers = [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
    'Accept: */*',
];

[$code, $body, $err] = httpRequest('POST', rtrim($baseUrl, '/') . '/v2/application/' . $appId . '/conversation', $headers, '{}', false);
if ($code < 200 || $code >= 300 || $body === '') {
    fwrite(STDERR, "创建会话失败：HTTP {$code} {$err}\n{$body}\n");
    exit(2);
}
$resp = json_decode($body, true);
$conversationId = is_array($resp) ? ($resp['data']['conversation_id'] ?? $resp['data']['id'] ?? $resp['conversation_id'] ?? '') : '';
if (!is_string($conversationId) || $conversationId === '') {
    fwrite(STDERR, "创建会话失败：未解析出 conversation_id\n{$body}\n");
    exit(3);
}

echo "conversation_id={$conversationId}\n";
echo "开始流式 invoke...\n";

$payload = [
    'app_id' => $appId,
    'conversation_id' => $conversationId,
    'stream' => true,
    'send_log_event' => false,
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'input',
                    'value' => (string)$message,
                ],
            ],
        ],
    ],
];

httpRequest('POST', rtrim($baseUrl, '/') . '/v3/application/invoke', $headers, json_encode($payload, JSON_UNESCAPED_UNICODE), true);
