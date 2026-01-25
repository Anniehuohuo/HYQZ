<?php
$url = 'https://open.bigmodel.cn/api/llm-application/open/v2/application/2014552270334828544/conversation';
$apiKey = 'cd8240fe28f94e298c7a2791844a6b13.Hmfo7lGpBbfcsFLC';

$data = [
    'prompt' => '你好',
    'requestId' => '123',
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Print directly
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) {
    echo $chunk;
    return strlen($chunk);
});

echo "Sending request...\n";
curl_exec($ch);
curl_close($ch);
echo "\nDone.\n";
