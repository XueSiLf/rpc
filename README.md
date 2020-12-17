# EasySwoole RPC
很多传统的Phper并不懂RPC是什么，RPC全称Remote Procedure Call，中文译为远程过程调用,其实你可以把它理解为是一种架构性上的设计，或者是一种解决方案。
例如在某庞大商场系统中，你可以把整个商场拆分为N个微服务（理解为N个独立的小模块也行），例如：
    
- 订单系统
- 用户管理系统
- 商品管理系统
- 等等 

那么在这样的架构中，就会存在一个Api网关的概念，或者是叫服务集成者。我的Api网关的职责，就是把一个请求
，拆分成N个小请求，分发到各个小服务里面，再整合各个小服务的结果，返回给用户。例如在某次下单请求中，那么大概
发送的逻辑如下：
- Api网关接受请求
- Api网关提取用户参数，请求用户管理系统，获取用户余额等信息，等待结果
- Api网关提取商品参数，请求商品管理系统，获取商品剩余库存和价格等信息，等待结果。
- Api网关融合用户管理系统、商品管理系统的返回结果，进行下一步调用（假设满足购买条件）
- Api网关调用用户管理信息系统进行扣款，调用商品管理系统进行库存扣减，调用订单系统进行下单（事务逻辑和撤回可以用请求id保证，或者自己实现其他逻辑调度）
- APi网关返回综合信息给用户

而在以上发生的行为，就称为远程过程调用。而调用过程实现的通讯协议可以有很多，比如常见的HTTP协议。而EasySwoole RPC采用自定义短链接的TCP协议实现，每个请求包，都是一个JSON，从而方便实现跨平台调用。

什么是服务熔断？
 
粗暴来理解，一般是某个服务故障或者是异常引起的，类似现实世界中的‘保险丝’，当某个异常条件被触发，直接熔断整个服务，而不是一直等到此服务超时。

什么是服务降级?

粗暴来理解，一般是从整体负荷考虑，就是当某个服务熔断之后，服务器将不再被调用，此时客户端可以自己准备一个本地的fallback回掉，返回一个缺省值，这样做，虽然服务水平下降，但好歹，比直接挂掉要强。
服务降级处理是在客户端实现完成的，与服务端没有关系。

什么是服务限流？

粗暴来理解，例如某个服务器最多同时仅能处理100个请求，或者是cpu负载达到百分之80的时候，为了保护服务的稳定性，则不在希望继续收到
新的连接。那么此时就要求客户端不再对其发起请求。因此EasySwoole RPC提供了NodeManager接口，你可以以任何的形式来
监控你的服务提供者，在getServiceNode方法中，返回对应的服务器节点信息即可。  

### EasySwoole RPC执行流程

服务端：  
注册RPC服务，创建相应的服务swoole table表（ps:记录调用成功和失败的次数） 
注册worker,tick进程  
  
woker进程监听：   
客户端发送请求->解包成相对应的格式->执行对应的服务->返回结果->客户端  

tick进程：  
注册定时器发送心跳包到本节点管理器   
启用广播：每隔几秒发送本节点各个服务信息到其他节点  
启用监听：监听其他节点发送的信息，发送相对应的命令（心跳|下线）到节点管理器处理  
进程关闭：主动删除本节点的信息，发送下线广播到其他节点  

![](easyswoole-rpc.png)

## Composer安装
```
composer require easyswoole/rpc=5.x
``` 

## 实例代码
### 服务端
```php
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\Tests\Service\ModuleOne;
use EasySwoole\Rpc\Tests\Service\Service;
use Swoole\Http\Server;
require 'vendor/autoload.php';

$config = new Config();
$config->getServer()->setServerIp('127.0.0.1');

$rpc = new Rpc($config);

$service = new Service();
$service->addModule(new ModuleOne());

$rpc->serviceManager()->addService($service);

$http = new Server('0.0.0.0', 9501);

$rpc->attachServer($http);

$http->on('request', function ($request, $response) use($rpc){
    $client = $rpc->client();
    $ctx1 = $client->addRequest('Service.Module');
    $ctx2 = $client->addRequest('Service.Module.action');
    $ctx2->setOnSuccess(function (Response $response){
        var_dump($response->getMsg());
    });
    $client->exec();
});

$http->start();
```