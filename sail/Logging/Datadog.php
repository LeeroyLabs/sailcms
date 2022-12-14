<?php

namespace SailCMS\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Formatter\JsonFormatter;
use RuntimeException;
use SailCMS\Sail;

class Datadog extends AbstractProcessingHandler
{
    private string $url = 'https://http-intake.logs.datadoghq.com/api/v2/logs';
    private string $key;

    /**
     *
     * @throws RunTimeException
     *
     */
    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('The curl extension is required to use the DataDog Logging Adapter');
        }

        parent::__construct($level, $bubble);

        $varname = setting('logging.datadog.api_key_identifier', 'DD_DEFAULT_KEY');
        $this->key = setting($varname, '');
    }

    protected function write(LogRecord $record): void
    {
        $defaultChannel = setting('logging.datadog.defaultChannel', 'app');
        $channel = $record->context['channel'] ?? $defaultChannel;

        $this->send($record, $channel);
    }

    private function send(LogRecord $record, string $channel): void
    {
        $headers = [
            'Accept: application/json',
            'DD-API-KEY:' . $this->key,
            'Content-Type:application/json'
        ];

        $url = $this->url;
        $ch = curl_init($url);

        $data = [
            'ddsource' => $channel,
            'ddtags' => env('environment', 'dev') . ',php:' . PHP_VERSION,
            'hostname' => Sail::siteId(),
            'message' => $record->formatted,
            'service' => 'sailcms'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}