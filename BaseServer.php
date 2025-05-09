<?php
declare(strict_types=1);

abstract class BaseServer {
    protected const VERSION = '0.1.0';
    protected const MCP_VERSION = '0.2.0';
    protected const JSON_RPC_VERSION = '2.0';
    protected const SERVER_NAME = 'NAME';
    
    protected $running = true;
    protected $initialized = false;
    protected $protocolVersion = null;
    protected $logFile;

    public function __construct() {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }
        $this->logFile = fopen("log.txt", "a");
    }

    protected function log($message) {
        if ($this->logFile) {
            fwrite($this->logFile, $message . "\n");
            fflush($this->logFile);
        }
    }

    protected function handleSignal($signal) {
        $this->running = false;
    }

    protected function createResponse($id, $result) {
        return [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $id,
            'result' => $result
        ];
    }

    protected function createError($id, $code, $message, $data = null) {
        return [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
                'data' => $data
            ]
        ];
    }

    protected function sendError($id, $code, $message, $data = null) {
        $this->sendResponse($this->createError($id, $code, $message, $data));
    }

    protected function sendResponse($response) {
        $json = json_encode($response) . "\n";
        $this->log($json);
        fwrite(STDOUT, $json);
        fflush(STDOUT);
    }

    protected function handleEmptyList($id, $type) {
        return $this->createResponse($id, [
            $type => []
        ]);
    }

    protected function handleInitialize($id, $params) {
        if (!isset($params['protocolVersion'])) {
            return $this->createError($id, 'InvalidParams', 'Missing protocolVersion');
        }

        $this->protocolVersion = $params['protocolVersion'];
        $this->initialized = true;

        return $this->createResponse($id, [
            'protocolVersion' => $this->protocolVersion,
            'serverInfo' => [
                'name' => static::SERVER_NAME,
                'version' => static::VERSION
            ],
            'capabilities' => [
                'tools'=> (object)[],
                'prompts'=> (object)[],
                'resources'=> (object)[]
            ]
        ]);
    }

    protected function handleToolCall($id, $params) {
        if (!isset($params['name'])) {
            return $this->createError($id, 'InvalidRequest', 'Missing tool name');
        }

        $toolName = $params['name'];
        $methodName = 'tool' . str_replace('_', '', ucwords($toolName, '_'));

        if (!method_exists($this, $methodName)) {
            return $this->createError($id, 'MethodNotFound', 'Unknown tool: ' . $toolName);
        }

        return $this->$methodName($id, $params['arguments'] ?? []);
    }

    protected function handleRequest($request): ?array {
        $id = $request['id'] ?? null;
        $method = strtolower($request['method']);

        switch ($method) {
            case 'notifications/initialized':
            case 'notifications/cancelled':
                return null;
            case 'ping':
                return $this->createResponse($id, (object)[]);
            case 'resources/list':
                return $this->handleEmptyList($id, 'resources');

            case 'prompts/list':
                return $this->handleEmptyList($id, 'prompts');

            case 'resources/templates/list':
                return $this->handleEmptyList($id, 'resourceTemplates');
                
            case 'tools/list':
                return $this->createResponse($id, [
                    'tools' => static::getTools()
                ]);

            case 'initialize':
                return $this->handleInitialize($id, $request['params'] ?? []);

            case 'tools/call':
                return $this->handleToolCall($id, $request['params'] ?? []);

            default:
                return $this->createError($id, 'MethodNotFound', 'Unknown method: ' . $method);
        }
    }

    public function run() {
        error_reporting(E_ALL);
        set_error_handler(function($severity, $message, $file, $line) {
            fwrite(STDERR, "PHP Error: $message in $file on line $line\n");
            return true;
        });

        while (ob_get_level()) ob_end_clean();

        while ($this->running) {
            try {
                $line = fgets(STDIN);
                if ($line === false) {
                    if (feof(STDIN)) {
                        break;
                    }
                    continue;
                }

                $this->log($line);
                $request = json_decode($line, true);
                if (!$request) {
                    $this->sendError(null, 'ParseError', 'Invalid JSON');
                    continue;
                }

                if (!isset($request['jsonrpc']) || $request['jsonrpc'] !== self::JSON_RPC_VERSION) {
                    $this->sendError($request['id'] ?? null, 'InvalidRequest', 'Invalid JSON-RPC version');
                    continue;
                }

                if (!isset($request['method'])) {
                    $this->sendError($request['id'] ?? null, 'InvalidRequest', 'Missing method');
                    continue;
                }

                if (!$this->initialized && $request['method'] !== 'initialize') {
                    $this->sendError($request['id'] ?? null, 'ServerNotInitialized', 'Server not initialized');
                    continue;
                }

                $response = $this->handleRequest($request);
                if ($response) {
                    $this->sendResponse($response);
                }

                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
            } catch (Exception $e) {
                $this->sendError(null, 'InternalError', $e->getMessage());
            }
        }
    }

    abstract protected static function getTools(): array;
}