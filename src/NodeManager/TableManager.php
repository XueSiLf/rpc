<?php
/**
 * Created by PhpStorm.
 * User: xcg
 * Date: 2019/5/30
 * Time: 15:00
 */

namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Rpc\ServiceNode;
use EasySwoole\Component\TableManager as esTableManager;
use EasySwoole\Utility\Random;
use Swoole\Table;

class TableManager implements NodeManagerInterface
{
    private $table;

    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    public function getServiceNodes(string $serviceName, ?string $version = null): array
    {
        $serviceNodes = [];
        foreach ($this->table as $serviceNode) {
            if (time() - $serviceNode->getLastHeartBeat() > 30) {
                $this->deleteServiceNode($serviceNode);
                continue;
            }
            if ($serviceNode['serviceName'] == $serviceName) {
                if ($version && $serviceNode['serviceVersion'] != $version) {
                    continue;
                }
                array_push($serviceNodes, $serviceNode);
            }
        }
        return $serviceNodes;
    }

    public function getServiceNode(string $serviceName, ?string $version = null): ?ServiceNode
    {
        $list = $this->getServiceNodes($serviceName, $version);
        if (empty($serviceNodes)) {
            return null;
        }
        return new ServiceNode(Random::arrayRandOne($list));
    }

    public function deleteServiceNode(ServiceNode $serviceNode): bool
    {
        $serviceNodeKey = $this->getServiceNodeKey($serviceNode->getServiceName(), $serviceNode->getNodeId());
        return $this->table->del($serviceNodeKey);
    }

    public function serviceNodeHeartBeat(ServiceNode $serviceNode): bool
    {
        if (empty($serviceNode->getLastHeartBeat())) {
            $serviceNode->setLastHeartBeat(time());
        }
        $serviceNodeKey = $this->getServiceNodeKey($serviceNode->getServiceName(), $serviceNode->getNodeId());
        return $this->table->set($serviceNodeKey, $serviceNode->toArray());
    }

    private function getServiceNodeKey(string $serviceName, string $serviceNodeId)
    {
        return implode('_', [$serviceName, $serviceNodeId]);
    }
}