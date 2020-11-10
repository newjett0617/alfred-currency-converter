<?php

declare(strict_types=1);

const USD = 'USD';
const GET = 'GET';

global $config;
$config = require_once __DIR__ . '/config.php';
require __DIR__ . '/vendor/autoload.php';

use Alfred\Workflows\Workflow;

function getBase($base = USD)
{
    $url = 'https://api.exchangerate-api.com/v4/latest/' . $base;
//    $url = 'http://127.0.0.1:8000/test-base.php';
    return sendApi($url);
}

function sendApi(string $url): array
{
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10, // 最多 10 次 redirection
        CURLOPT_TIMEOUT => 10, // 10 秒後 timeout
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => GET,
    ]);

    $result = curl_exec($curl);
    $errorMsg = curl_error($curl);
    $errorCode = curl_errno($curl);
    curl_close($curl);

    if ($result !== false) {
        $result = json_decode($result, true);
    }

    if ($errorCode !== 0) {
        file_put_contents(__DIR__ . '/logs/api.log', json_encode([
            'result' => $result,
            'errorCode' => $errorCode,
            'errorMsg' => $errorMsg,
        ]));
        return [
            'status' => false,
            'data' => [
                'msg' => $errorMsg,
                'code' => $errorCode,
            ],
        ];
    }

    return [
        'status' => true,
        'data' => $result,
    ];
}

function parseBase(array $result): array
{
    $rates = [];

    foreach ($result['rates'] ?? [] as $k => $rate) {
        if ($rate !== 0 && availableCurrency($k)) {
            $rates[$k] = $rate;
        }
    }

    return $rates;
}

function parseArgs(array $argv): array
{
    $data = [USD, '1'];

    if (count($argv) === 2) {
        $matches = [];
        $re = '/([0-9.]*)\s*([a-zA-Z]*)/';
        preg_match($re, $argv[1], $matches);
        $data[0] = $matches[2] ? strtoupper($matches[2]) : USD;
        $data[1] = $matches[1];
    }

    return $data;
}

function errorOutput($title, $subtitle = ''): void
{
    $workflow = new Workflow;

    $workflow->result()
        ->title($title)
        ->subtitle($subtitle);

    echo $workflow->output();
    exit(1);
}

function availableCurrency(string $currency): bool
{
    global $config;

    return in_array($currency, array_keys($config));
}

function dd(...$arg): void
{
    var_dump(...$arg);
    die();
}

// -------------------- main --------------------

list($currencyTo, $amount) = parseArgs($argv);

if (!availableCurrency($currencyTo)) {
    errorOutput('base not support');
}

$base = getBase($currencyTo);

if (!$base['status']) {
    errorOutput(
        $base['data']['msg'],
        'cURL(' . $base['data']['code'] . ') error'
    );
}

$data = [];
$baseRate = parseBase($base['data']);

foreach ($config as $k => $v) {
    if ($currencyTo === $k) {
        continue;
    }

    $data[$k] = $baseRate[$k] * $amount;
}

$workflow = new Workflow;
foreach ($data as $k => $v) {
    $workflow->result()
        ->title(number_format($v, 2) . ' ' . $config[$k] . '(' . $k . ')')
        ->icon('flags/' . $k . '.png');
}
echo $workflow->output();
