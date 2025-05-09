#  轻量级PHP MCP服务器实现

[![Version](https://img.shields.io/badge/version-0.8.0-blue.svg)](https://github.com/your-repo/mcp-timeserver) [![License](https://img.shields.io/badge/license-MIT-green.svg)](https://opensource.org/licenses/MIT)

## 简介

在AI领域，MCP（Message Control Protocol）服务器近年来成为实现各种功能的重要工具。然而，官方MCP SDK往往过于臃肿，代码量庞大且复杂，对于简单的需求显得“重量级”。更糟糕的是，许多冷门编程语言没有官方SDK支持，给开发者带来了不小的困扰。

本项目是一个基于PHP手写实现的轻量级MCP服务器，完全绕过官方SDK的限制。
演示`index.php`它基于一个通用的`BaseServer`基类打造，目前实现了获取当前时间的工具功能。这个项目旨在为开发者提供一个简单、高效的MCP服务器解决方案，同时作为模板帮助你快速扩展自己的业务功能。




### 1. 了解MCP服务器与基类功能

在我们动手编写业务类之前，先简单了解一下MCP服务器的作用和提供的`BaseServer`基类。MCP服务器本质上是一个基于JSON-RPC 2.0协议的服务端程序，用于响应客户端的请求，比如初始化、工具调用等。而`BaseServer`已经帮我们处理了底层的协议交互、日志记录、错误处理等通用功能，我们只需要专注于业务逻辑的实现。

基类中已经内置了以下核心功能：
- **初始化处理**：通过`initialize`方法设置协议版本和服务器信息。
- **工具调用**：通过`tools/call`方法动态调用我们定义的工具。
- **响应与错误处理**：提供了创建和发送响应或错误信息的方法。

因此，业务类的核心任务是定义具体的服务名称、版本号，以及实现具体的工具功能。

### 2. 业务类的编写步骤

接下来，就带大家开发一个获取当前时间的**MCP服务器**，详细拆解如何基于`BaseServer`编写一个MCP服务器业务类。

#### 步骤1：继承基类并设置基本信息

首先，创建一个新的类`TimeServer`，继承`BaseServer`，并定义一些基本的常量，比如服务器名称和版本号。这里我们还设置了默认时区，以便在获取时间时使用。

```php
class TimeServer extends BaseServer {
    protected const DEFAULT_TIMEZONE = 'Asia/Shanghai'; // 默认时区
    protected const SERVER_NAME = 'time-server'; // 服务器名称
    protected const VERSION = '0.8.0'; // 版本号
}
```

这些常量会影响到服务器初始化时的响应信息，确保客户端能识别我们的服务。

#### 步骤2：定义工具列表

MCP服务器的一个重要功能是提供“工具”（tools），客户端可以通过调用这些工具来实现特定功能。在`TimeServer`中，我们提供一个名为`get_time`的工具，用于获取当前时间。

我们需要重写基类的抽象方法`getTools()`，返回一个包含工具定义的数组。每个工具需要指定名称、描述和输入参数的模式（schema）。

```php
protected static function getTools(): array {
    return [
        [
            'name' => 'get_time', // 工具名称
            'description' => '获取当前时间', // 工具描述
            'inputSchema' => [ // 输入参数模式
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
```

这段代码告诉客户端，我们的服务器支持一个叫`get_time`的工具，客户端可以选择性地传入一个时区参数。

#### 步骤3：实现工具的具体逻辑

定义好工具列表后，就需要实现工具的具体功能。基类会根据客户端请求的工具名称，动态调用对应的方法。工具名称`get_time`会映射到方法名`toolGetTime`（注意大小写和下划线的转换规则）。

在这个方法中，我们获取传入的时区参数（如果没有则使用默认时区），设置时区后获取当前时间，并以JSON格式返回结果。如果时区无效，则返回错误信息。

```php
protected function toolGetTime($id, $arguments) {
    $timezone = $arguments['timezone'] ?? self::DEFAULT_TIMEZONE; // 获取时区参数
  
    try {
        date_default_timezone_set($timezone); // 设置时区
        $time = date('Y-m-d H:i:s'); // 获取当前时间
      
        return $this->createResponse($id, [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        'time' => $time,
                        'timezone' => $timezone
                    ], JSON_PRETTY_PRINT) // 返回格式化的时间和时区信息
                ]
            ]
        ]);
    } catch (Exception $e) {
        return $this->createError($id, 'InvalidParams', 'Invalid timezone: ' . $timezone); // 错误处理
    }
}
```

这里我们使用了基类的`createResponse`和`createError`方法来构建响应，确保与客户端的交互符合协议规范。

#### 步骤4：启动服务器

最后一步很简单，实例化`TimeServer`并调用`run`方法启动服务器。`run`方法是基类中已经实现的，它会持续监听标准输入的请求并处理。

```php
$server = new TimeServer();
$server->run();
```

### 3. 测试与优化建议

编写完成后，你可以通过命令行启动服务器，并用支持MCP协议的客户端进行测试。发送一个`initialize`请求初始化服务器，然后调用`tools/call`方法，传入工具名称`get_time`和可选的时区参数，查看返回的时间是否正确。

以下是一些优化建议：
- **日志查看**：基类会将请求和响应记录到`log.txt`文件中，方便调试。
- **错误处理**：确保对各种异常情况（如无效时区）有完善的错误提示。
- **工具扩展**：可以添加更多工具，比如获取日期、计算时间差等，丰富服务器功能。

### 4. 其他语言

代码很短，不到200行，其他语言可以直接参考实现

## 结语

通过以上步骤，你已经成功用PHP手搓了一个轻量级的MCP服务器业务类`TimeServer`，实现了获取当前时间的功能。相比臃肿的官方SDK，这种方式不仅简单直接，还能灵活适配各种需求。希望这个教程能帮你快速上手MCP服务器开发，解决SDK带来的痛点。如果你有更多功能需求，不妨基于这个基类继续扩展，打造属于自己的高效工具服务器！