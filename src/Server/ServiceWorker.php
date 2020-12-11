<?php


namespace EasySwoole\Rpc\Server;


use EasySwoole\Component\Process\Socket\AbstractTcpProcess;
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Network\Protocol;
use EasySwoole\Rpc\Network\Request;
use EasySwoole\Rpc\Network\Response;
use EasySwoole\Rpc\Service\AbstractService;
use Swoole\Coroutine\Socket;

class ServiceWorker extends AbstractTcpProcess
{
    /** @var Config */
    private $config;
    private $serviceList = [];

    function run($arg)
    {
        $this->config = $arg['config'];
        $this->serviceList = $arg['serviceList'];
        parent::run($arg);
    }

    function onAccept(Socket $socket)
    {
        $response = new Response();

        $header = $socket->recvAll(4, $this->config->getServer()->getNetworkReadTimeout());
        if (strlen($header) != 4) {
            $response->setStatus($response::STATUS_PACKAGE_READ_TIMEOUT);
            $this->reply($socket, $response);
            return;
        }

        $allLength = Protocol::packDataLength($header);
        if ($allLength > $this->config->getServer()->getMaxPackageSize()) {
            $response->setStatus($response::STATUS_ILLEGAL_PACKAGE);
            $this->reply($socket, $response);
            return;
        }
        $data = $socket->recvAll($allLength, $this->config->getServer()->getNetworkReadTimeout());
        if (strlen($data) != $allLength) {
            $response->setStatus($response::STATUS_PACKAGE_READ_TIMEOUT);
            $this->reply($socket, $response);
            return;
        }
        $request = json_decode($data, true);
        if(!is_array($request)){
            $response->setStatus($response::STATUS_ILLEGAL_PACKAGE);
            $this->reply($socket, $response);
            return;
        }
        $request = new Request($request);
        if(isset($this->serviceList[$request->getService()])){
            /** @var AbstractService $service */
            //克隆模式，否则如果定义了成员属性会发生协程污染
            $service = clone $this->serviceList[$request->getService()];
            $service->__exec($request,$response,$socket);
        }else{
            $response->setStatus($response::STATUS_SERVICE_NOT_EXIST);
        }
        $this->reply($socket, $response);
    }

    protected function reply(Socket $clientSocket, Response $response)
    {
        $str = json_encode($response,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $str = Protocol::pack($str);
        $clientSocket->sendAll($str);
        $clientSocket->close();
    }
}