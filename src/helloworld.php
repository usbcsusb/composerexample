<?php
declare(strict_types=1);
use Swow\Coroutine;
use Swow\CoroutineException;
use Swow\Errno;
use Swow\Http\Protocol\ProtocolException as HttpProtocolException;
use Swow\Http\Status as HttpStatus;
use Swow\Psr7\Server\Server;
use Swow\Socket;
use Swow\SocketException;


require './vendor/autoload.php';;

# ini_set 指定环境变量 用于限制内存占用
ini_set('memory_limit', '1G');

# 指定 SERVER_HOST  0.0.0.0 代表可以被任意ip访问
$host = getenv('SERVER_HOST') ?: '0.0.0.0';
# 指定 访问端口 如果你的服务部署在本地 可以通过此地址访问http服务  http://localhost:9764
$port = (int) ((($port = getenv('SERVER_PORT')) !== '' && $port !== false) ? $port : 9764);
$backlog = (int) (getenv('SERVER_BACKLOG') ?: Socket::DEFAULT_BACKLOG);

#创建一个 httpserver
$server = new Server();
#将上述的 host 和 port 指定 ，监听访问
$server->bind($host, $port)->listen($backlog);

echo "Server started at http://{$host}:{$port}\n";

while (true) {
    try {
        $connection = null;
        $connection = $server->acceptConnection();
        Coroutine::run(static function () use ($connection): void {
            try {
                while (true) {
                    $request = null;
                    try {
                        $request = $connection->recvHttpRequest();
                        switch ($request->getUri()->getPath()) {
                            case '/':
                                $connection->respond(sprintf('Server start successful !'));
                                break;
                            case '/hello':
                                $connection->respond(sprintf('Hello World'));
                                break;
                            default:
                                $connection->error(HttpStatus::NOT_FOUND);
                        }
                    } catch (HttpProtocolException $exception) {
                        $connection->error($exception->getCode(), $exception->getMessage(), close: true);
                        break;
                    }
                }
            } catch (Exception) {
                // you can log error here
            } finally {
                $connection->close();
            }
        });
    } catch (SocketException|CoroutineException $exception) {
        if (in_array($exception->getCode(), [Errno::EMFILE, Errno::ENFILE, Errno::ENOMEM], true)) {
            sleep(1);
        } else {
            break;
        }
    }
}
