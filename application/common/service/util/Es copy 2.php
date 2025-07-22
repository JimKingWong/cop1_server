<?php
namespace app\common\service;

use Elasticsearch\ClientBuilder;

class EsClient
{
    protected $host = null;

    protected $client = null;

    protected $prefix = null;

    protected $config = null;

    private static $instance;

    // 单例模式
    private function __construct()
    {
        $config = config('es');

        $this->config = $config;

        $this->prefix = $config['prefix'];

        $this->host = [$config['esconfig']];

        $this->client = ClientBuilder::create()           // 创建 ClientBuilder对象
            ->setHosts($this->host)
            ->setConnectionPool('\Elasticsearch\ConnectionPool\SimpleConnectionPool', [])
            ->setRetries(10)->build();    // 设置 hosts
    }

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 创建索引
     * @param string $index 索引名
     * @param array $mappings 映射配置
     * @return array
     */
    public function createIndex(string $index, array $mappings = [])
    {
        $params = [
            'index' => $index,
            'body'  => [
                'settings' => [
                    'number_of_shards' => 3,
                    'number_of_replicas' => 2
                ]
            ]
        ];

        if (!empty($mappings)) {
            $params['body']['mappings'] = $mappings;
        }

        return $this->client->indices()->create($params);
    }

    /**
     * 删除索引
     * @param string $index 索引名
     * @return array
     */
    public function deleteIndex(string $index)
    {
        return $this->client->indices()->delete(['index' => $index]);
    }

    /**
     * 更新索引映射（添加新字段）
     * @param string $index 索引名
     * @param array $properties 新增字段映射
     * @return array
     */
    public function updateMapping(string $index, array $properties)
    {
        return $this->client->indices()->putMapping([
            'index' => $index,
            'body' => [
                'properties' => $properties
            ]
        ]);
    }

    /**
     * 添加/更新文档
     * @param string $index 索引名
     * @param mixed $id 文档ID
     * @param array $data 文档数据
     * @return array
     */
    public function indexDocument(string $index, $id, array $data)
    {
        $params = [
            'index' => $index,
            'id'    => $id,
            'body'  => $data
        ];

        return $this->client->index($params);
    }

    /**
     * 删除文档
     * @param string $index 索引名
     * @param mixed $id 文档ID
     * @return array
     */
    public function deleteDocument(string $index, $id)
    {
        return $this->client->delete([
            'index' => $index,
            'id'    => $id
        ]);
    }

    /**
     * 搜索文档
     * @param string $index 索引名
     * @param array $query DSL查询
     * @return array
     */
    public function search(string $index, array $query)
    {
        return $this->client->search([
            'index' => $index,
            'body'  => $query
        ]);
    }

    /**
     * 部分更新文档
     * @param string $index 索引名
     * @param mixed $id 文档ID
     * @param array $partialData 部分数据
     * @return array
     */
    public function partialUpdate(string $index, $id, array $partialData)
    {
        return $this->client->update([
            'index' => $index,
            'id'    => $id,
            'body'  => ['doc' => $partialData]
        ]);
    }
}