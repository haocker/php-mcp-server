#!/usr/bin/env php
<?php

include_once 'BaseServer.php';

class TimeServer extends BaseServer {
    protected const DEFAULT_TIMEZONE = 'Asia/Shanghai';
    protected const SERVER_NAME = 'time-server';
    protected const VERSION = '0.8.0';


    protected static function getTools(): array {
        return [
            [
                'name' => 'get_time',
                'description' => '获取当前时间',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'timezone' => [
                            'type' => 'string',
                            'description' => '时区 (可选，默认为 Asia/Shanghai)'
                        ]
                    ]
                ]
            ]
        ];
    }

    protected function toolGetTime($id, $arguments) {
        $timezone = $arguments['timezone'] ?? self::DEFAULT_TIMEZONE;
        
        try {
            date_default_timezone_set($timezone);
            $time = date('Y-m-d H:i:s');
            
            return $this->createResponse($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'time' => $time,
                            'timezone' => $timezone
                        ], JSON_PRETTY_PRINT)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $this->createError($id, 'InvalidParams', 'Invalid timezone: ' . $timezone);
        }
    }
}

$server = new TimeServer();
$server->run();