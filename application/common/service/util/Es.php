<?php

namespace app\common\service\util;

use Elasticsearch\ClientBuilder;

class Es
{
    protected $host = null;

    protected $client = null;

    protected $prefix = null;

    protected $config = null;

    public function __construct()
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

    /**
     * 后台分页列表
     */
    public function record($index, array $conditions, $from = 0, $size = 10)
    {
        $index = $this->prefix . '_' . $index; // 添加前缀
        $params = [
            'index' => $index,
            'body'  => [
                'query' => $this->buildQuery($conditions),
                'size'   => $size,
                'from'   => $from
            ]
        ];

        $sorts = [
            [
                'field' => 'createtime', 
                'order' => 'desc',
            ]
        ];

        // 添加排序条件
        if(!empty($sorts)){
            $params['body']['sort'] = $this->buildSorts($sorts);
        }

        $response = $this->client->search($params);
        $list = $response['hits']['hits'];
        $data = [];
        foreach ($list as $record) {
            $data[] = $record['_source'];
        }
        $retval = [
            'list' => $data,
            'total' => $response['hits']['total']['value']
        ];
        return $retval;
    }

    /**
     * 多条件组合查询
     * @param string $index 索引名
     * @param array $conditions 查询条件（结构化数组）
     * @param int $from 分页起始位置
     * @param int $size 每页数量
     * @return array 查询结果
     */
    public function multiSearch($index, array $conditions)
    {
        $index = $this->prefix . '_' . $index; // 添加前缀
        $params = [
            'index' => $index,
            'body'  => [
                'query' => $this->buildQuery($conditions),
                'size'   => 1000,
            ]
        ];

        $sorts = [
            [
                'field' => 'createtime', 
                'order' => 'desc',
            ]
        ];

        // 添加排序条件
        if(!empty($sorts)){
            $params['body']['sort'] = $this->buildSorts($sorts);
        }

        $response = $this->client->search($params);
        $list = $response['hits']['hits'];
        $data = [];
        foreach ($list as $record) {
            $data[] = $record['_source'];
        }
        return $data;
    }

    /**
     * 分组聚合查询
     * @param array $conditions 查询条件
     * @param string $groupField 分组字段
     * @param array $sumFields 需要求和的字段
     * @param array $options 其他选项
     * @return array
     * @throws Exception
     */
    public function groupAggregation($index, array $conditions, string $groupField, array $sumFields = [], array $options = [])
    {
        $index = $this->prefix . '_' . $index; // 添加前缀
        // 构建请求参数
        $params = [
            'index' => $index,
            'body'  => [
                'query' => $this->buildQuery($conditions),
                'aggs'  => $this->buildGroupAggs($groupField, $sumFields, $options)
            ],
            'size'  => 0 // 不需要返回原始文档
        ];
        
        // 添加排序
        if (!empty($options['group_sort'])) {
            $params['body']['aggs']['group_data']['terms']['order'] = $options['group_sort'];
        }

        // 添加分组大小限制
        if (isset($options['group_size'])) {
            $params['body']['aggs']['group_data']['terms']['size'] = $options['group_size'];
        }

        $response = $this->client->search($params);
        return $this->parseGroupResponse($response, $sumFields);
    }

    /**
     * 构建复合查询
     * @param array $conditions
     * @return array
     */
    private function buildQuery(array $conditions)
    {
        $boolQuery = ['bool' => ['must' => []]];
        // dd($conditions);
        foreach ($conditions as $condition) {
            if (!isset($condition['type'], $condition['field'], $condition['value'])) {
                continue;
            }

            $queryType = $condition['type'];
            $field = $condition['field'];
            $value = $condition['value'];

            switch ($queryType) {
                case 'term':
                    $boolQuery['bool']['must'][] = ['term' => [$field => $value]];
                    break;
                case 'terms':
                    $boolQuery['bool']['must'][] = ['terms' => [$field => $value]];
                    break;
                case 'range':
                    $boolQuery['bool']['must'][] = ['range' => [$field => $value]];
                    break;
                case 'match':
                    $boolQuery['bool']['must'][] = ['match' => [$field => $value]];
                    break;
                case 'wildcard':
                    $boolQuery['bool']['must'][] = ['wildcard' => [$field => $value]];
                    break;
                case 'exists':
                    $boolQuery['bool']['must'][] = ['exists' => ['field' => $field]];
                    break;
                default:
                    break;
            }
        }
        // dd($boolQuery);
        return $boolQuery;
    }

    /**
     * 构建分组聚合
     * @param string $groupField 分组字段
     * @param array $sumFields 求和字段
     * @param array $options 选项
     * @return array
     */
    private function buildGroupAggs(string $groupField, array $sumFields, array $options)
    {
        $aggs = [
            'group_data' => [
                'terms' => [
                    'field' => $groupField,
                    'size' => $options['group_size'] ?? 99999999 // 默认返回100个分组
                ]
            ]
        ];

        // 添加子聚合（字段求和）
        if (!empty($sumFields)) {
            foreach ($sumFields as $field) {
                $aggs['group_data']['aggs'][$field . '_sum'] = [
                    'sum' => ['field' => $field]
                ];
            }
        }

        // 添加文档计数
        $aggs['group_data']['aggs']['doc_count'] = [
            'value_count' => ['field' => $groupField]
        ];

        return $aggs;
    }

    /**
     * 解析分组响应结果
     * @param array $response
     * @param array $sumFields
     * @return array
     */
    private function parseGroupResponse(array $response, array $sumFields)
    {
        $groups = [];
        $buckets = $response['aggregations']['group_data']['buckets'] ?? [];

        foreach ($buckets as $bucket) {
            $groupItem = [
                'key' => $bucket['key'],
                'doc_count' => $bucket['doc_count']
            ];

            // 处理求和字段
            foreach ($sumFields as $field) {
                $sumKey = $field . '_sum';
                $groupItem[$field . '_sum'] = $bucket[$sumKey]['value'] ?? 0;
            }

            $groups[] = $groupItem;
        }

        $data = [];
        foreach($groups as &$group) {
            $data[$group['key']] = $group;
        }

        // 返回平台为索引的数组
        return $data;

        return [
            'total' => $response['hits']['total']['value'] ?? 0,
            'groups' => $groups,
            'took' => $response['took'] ?? 0
        ];
    }

    /**
     * 构建排序结构
     * @param array $sorts 排序配置
     * @return array
     */
    private function buildSorts(array $sorts)
    {
        $sortArray = [];
        
        foreach ($sorts as $sort) {
            if (isset($sort['type']) && $sort['type'] === 'script') {
                // 脚本排序
                $sortArray[] = [
                    '_script' => [
                        'type' => 'number',
                        'script' => [
                            'lang' => 'painless',
                            'source' => $sort['script'],
                            'params' => $sort['params'] ?? []
                        ],
                        'order' => $sort['order'] ?? 'desc'
                    ]
                ];
            } 
            elseif (isset($sort['type']) && $sort['type'] === 'geo_distance') {
                // 地理位置排序
                $sortArray[] = [
                    '_geo_distance' => [
                        $sort['field'] => $sort['points'],
                        'order' => $sort['order'] ?? 'asc',
                        'unit' => $sort['unit'] ?? 'km',
                        'distance_type' => $sort['distance_type'] ?? 'arc'
                    ]
                ];
            }
            else {
                // 标准字段排序
                $sortItem = [
                    $sort['field'] => [
                        'order' => $sort['order'] ?? 'desc'
                    ]
                ];
                
                // 添加可选参数
                if (isset($sort['format'])) {
                    $sortItem[$sort['field']]['format'] = $sort['format'];
                }
                if (isset($sort['mode'])) {
                    $sortItem[$sort['field']]['mode'] = $sort['mode'];
                }
                if (isset($sort['missing'])) {
                    $sortItem[$sort['field']]['missing'] = $sort['missing'];
                }
                
                $sortArray[] = $sortItem;
            }
        }
        
        return $sortArray;
    }


    /**  
     * 创建索引  
     *  
     * @param string $index 索引名称  
     * @param array $body 索引的body定义，包含mappings和settings等  
     * @return bool|array 创建结果，如果失败返回false，成功返回Elasticsearch的响应  
     */  
    public function createIndex($index, $body)  
    {  
        $index = $this->prefix . '_' . $index;
        try{  
            $params = [  
                'index' => $index,  
                'body' => $body,  
            ];  
  
            $response = $this->client->indices()->create($params);  
  
            if($response['acknowledged']){  
                return $response; // 返回创建成功的响应  
            } else {  
                return false; // 创建失败  
            }  
        }catch (\Exception $e){  
            return false;  
        }  
    }

    /**
     * 添加游戏记录
     */
    public function addGameRecord($user, $game, $betInfoArr, $table_record)
    {
        $gameRecordParam['body'] = [
            'admin_id'          => $user->admin_id,
            'user_id'           => $user->id,
            'game_id'           => $game['game_id'],
            'image'             => $game['image'],
            'transaction_id'    => $betInfoArr['transaction_id'],
            'bet_amount'        => $betInfoArr['bet_amount'],
            'win_amount'        => bcmul($betInfoArr['win_amount'], 1, 2),
            'transfer_amount'   => bcmul($betInfoArr['transfer_amount'], 1, 2), // 输赢
            'typing_amount'     => 0,
            'balance'           => $user->money, // 用户余额
            'is_fake'           => $betInfoArr['is_fake'],
            'platform'          => $game['platform'] ?? 0,
            'createtime'        => time(),
        ];
        $gameRecordParam['index'] = $table_record;
        $gameRecordParam['type'] = '_doc';
        $this->add($gameRecordParam);
    }

     /**
     * 添加cq游戏记录
     */
    public function addcqGameRecord($user, $game, $betInfoArr, $table_record)
    {
        $gameRecordParam['body'] = [
            'admin_id'          => $user->admin_id,
            'user_id'           => $user->id,
            'game_id'           => $game['game_id'],
            'image'             => $game['image'],
            'roundid'           => $betInfoArr['roundid'],
            'mtcode'            => $betInfoArr['mtcode'],
            'amount'            => $betInfoArr['amount'],
            'bet_amount'        => $betInfoArr['bet_amount'],
            'win_amount'        => bcmul($betInfoArr['win_amount'], 1, 2),
            'transfer_amount'   => bcmul($betInfoArr['transfer_amount'], 1, 2), // 输赢
            'balance'           => $user->money, // 用户余额
            'is_fake'           => $betInfoArr['is_fake'],
            'platform'          => $game['platform'] ?? 0,
            'createtime'        => time(),
        ];
        $gameRecordParam['index'] = $table_record;
        $gameRecordParam['type'] = '_doc';
        $this->add($gameRecordParam);
    }

    /**
     * 插入数据
     */
    public function add($params) 
    {
        // 加个前缀
        $params['index'] = $this->prefix . '_' . $params['index'];
        $this->client->index($params);
    }

    /**
     * 添加 indices 方法
     */
    public function indices()
    {
        return $this->client->indices();
    }

    /**
     * 插入游戏记录
     */
    public function addGameResult($param) 
    {
       
        $insert_data = [];
        $insert_data['body'] = [
            'result_type' => $param['type'],
            'result_json' => $param['result_json'],
            'createtime' => time(),
        ];
        $insert_data['index'] = 'ks_game_result';
        $insert_data['type'] = '_doc';
        $ret = $this->client->index($insert_data);
    }

    /**
     * 通过交易ID查询游戏记录
     */
    public function searchByTransactionId($transaction_id, $esIndex) 
    {
        $esIndex = $this->prefix . '_' . $esIndex;
        $params = [
            'index' => $esIndex,
            'type'  => '_doc',
            'body'  => [
                'query' => [
                    'bool' => [
                        'filter' => [
                            'term' => [ 'transaction_id' => $transaction_id ]
                        ]
                    ]
                ]
            ]
        ];
        
        $response = $this->client->search($params);
        // 单条记录
        return $response['hits']['hits'][0]['_source'];
    }

    /**
     * 通过交易ID查询游戏记录
     */
    public function searchGameRecord($transaction_id, $esIndex)
    {
        $esIndex = $this->prefix . '_' . $esIndex;
        $params = [
            'index' => $esIndex,
            'type'  => '_doc',
            'body'  => [
                'query' => [
                    'bool' => [
                        'filter' => [
                            'term' => [ 'transaction_id' => $transaction_id ]
                        ]
                    ]
                ]
            ]
        ];
        
        $response = $this->client->search($params);
        // 单条记录
        return $response['hits']['hits'][0]['_source'];
    }

    /**
     * 通过玩家ID查询数据
     */
    public function searchByUserId($pageParam, $esIndex) 
    {
        $pp = [
            "from" => $pageParam['offset'],
            "size" => $pageParam['limit'],
            "sort" => [
                "createtime" => [
                    "order" => "desc"
                ]
            ]
        ];
        if(array_key_exists('createtime', $pageParam['condition'])){
            $timestr = explode(' - ', $pageParam['condition']['createtime']);
            $pageParam['start_time'] = strtotime($timestr[0]);
            $pageParam['end_time'] = strtotime($timestr[1]);
            unset($pageParam['condition']['createtime']);
        }
        if($pageParam['condition']){
            $pp['query']['bool']['must'] = [
                'term' => $pageParam['condition']
            ];
            if(array_key_exists('start_time', $pageParam)){
                $pp['query']['bool']['filter']['range'] = [
                    'createtime' => [
                        "gte" => $pageParam['start_time'],
                        "lte" => $pageParam['end_time'],
                    ]
                ];
            }
        }

        $esIndex = $this->prefix . '_' . $esIndex;
        $params = [
            'index' => $esIndex,
            'type' => '_doc',
            'body' => $pp
        ];
        $response = $this->client->search($params);
        return $response['hits'];
    }

    /**
     * 用户游戏记录
     */
    public function searchRecord($user_id, $esIndex) 
    {
        $esIndex = $this->prefix . '_' . $esIndex;
        $params = [
            'index'     => $esIndex,
            'type'      => '_doc',
            'body'      => [
                'query' => [
                    'bool' => [
                        'filter' => [
                            'term' => [ 'user_id' => $user_id]
                        ],
                    ]
                ],
                "from" => 0,
                "size" => 200,
                "sort" =>[
                    "createtime" => [
                        "order" => "desc"
                    ]
                ]
            ]
        ];
        
        $response = $this->client->search($params);
        $list = $response['hits']['hits'];
        $data = [];
        foreach ($list as $record) {
            $data[] = $record['_source'];
        }
        return $data;
    }

    /**
     * 通过玩家ID和日期查询数据
     */
    public function searchByDate($esIndex, $where, $startTime, $endTime) 
    {
        $esIndex = $this->prefix . '_' . $esIndex;
        $params = [
            'index' => $esIndex,
            'type' => '_doc',
            'body' => [
                'query' => [
                    'constant_score' => [
                        'filter' => [
                            'bool' => [
                                'must' => [
                                    'term' => ['user_id' => $where['user_id']],
                                    'term' => ['platform' => $where['platform']],
                                ],
                                'filter' => [
                                    'range' => [
                                        'createtime' => [
                                            "gte" => $startTime,
                                            "lte" => $endTime,
                                        ]
                                    ]
                                ],
                            ]
                        ],
                    ]
                ],
                "from" => 0,
                "size" => 200,
                "sort" =>[
                    "createtime" => [
                        "order" => "desc"
                    ]
                ]
            ]
        ];
        $response = $this->client->search($params);
        $list = $response['hits']['hits'];
        $data = [];
        foreach ($list as $record) {
            $data[] = $record['_source'];
        }
        return $data;
    }

    /**
     * 按日期统计金额
     */
    public function sumAmountByDate($esIndex, $startTime, $endTime, $sumFeild) 
    {
        $esIndex = $this->prefix . '_' . $esIndex;
        $params = [
            'index' => $esIndex,
            'type' => '_doc',
            'body' => [
                'query' => [
                    'range' => [
                        'createtime' => [
                            "gte" => $startTime,
                            "lte" => $endTime,
                        ]
                    ]
                ],
                "aggs" => [
                    "sum_transfer_amount" => [
                        "sum" => [
                            "field"=> $sumFeild
                        ]
                    ]
                ]
            ]
        ];
        $response = $this->client->search($params);
        return $response['aggregations']['sum_transfer_amount']['value'];
    }

    public function sumAmount($esIndex, $userId, $sumFeild) 
    {
       
        $params = [
            'index' => $esIndex,
            'type' => '_doc',
            'body' => [
                'query' => [
                    'bool' => [
                        'filter' => [
                            'term' => [ 'user_id' => $userId ]
                        ],
                    ]
                ],
                "aggs" => [
                    "sum_transfer_amount" => [
                        "sum" => [
                            "field"=> $sumFeild
                        ]
                    ]
                ]
            ]
        ];
        $response = $this->client->search($params);
        return $response['aggregations']['sum_transfer_amount']['value'];
    }
    
    /**
     * 检查索引是否存在
     */
    public function checkIndexExists($index)
    {
        $index = $this->prefix . '_' . $index;
        return $this->client->indices()->exists(['index' => $index]);
    }

    /**
     * 删除索引
     * @param string $index 索引名
     * @return array
     */
    public function deleteIndex(string $index)
    {
        $index = $this->prefix . '_' . $index;
        return $this->client->indices()->delete(['index' => $index]);
    }
}