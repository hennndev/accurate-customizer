<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter as MonologJsonFormatter;
use Monolog\LogRecord;

class JsonFormatter extends MonologJsonFormatter
{
    public function __construct()
    {
        parent::__construct();
    }

    public function format(LogRecord $record): string
    {
        $data = [
            'timestamp' => $record->datetime->format('Y-m-d H:i:s'),
            'level' => $record->level->getName(),
            'message' => $record->message,
            'context' => $record->context,
        ];

        if (!empty($record->extra)) {
            $data['extra'] = $record->extra;
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
