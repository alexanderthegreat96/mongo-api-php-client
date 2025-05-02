<?php

namespace Alexanderthegreat96\MongoApiClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MongoApiClient
{
    private array $globalHeaders;
    private string $baseUrl;
    private ?string $dbName    = null;
    private ?string $tableName = null;
    private int $perPage       = 0;
    private int $page          = 0;
    private ?string $groupBy   = null;
    private bool $asPipeline   = false;
    private array $whereQuery  = [];
    private array $orWhereQuery = [];
    private array $sortByList  = [];
    private Client $guzzle;

    private array $operatorMap = [
        '='       => '=',
        '!='      => '!=',
        '<'       => '<',
        '<='      => '<=',
        '>'       => '>',
        '>='      => '>=',
        'like'    => 'ilike',
        'not_like' => 'not_like',
        'between' => 'between'
    ];
    private array $sortOrder = ['asc', 'desc'];

    public function __construct(
        string $serverUrl,
        int    $serverPort,
        string $scheme = 'http',
        string $apiKey = ''
    ) {
        $this->baseUrl       = "$scheme://$serverUrl:$serverPort/db";
        $this->globalHeaders = ['Accept' => 'application/json', 'api_key' => $apiKey];
        $this->guzzle        = new Client(['timeout' => 5.0]);
    }

    private function sendRequestWithRetry(
        string $method,
        string $url,
        array  $params = [],
        array  $data   = [],
        array  $headers = [],
        int    $retries = 3,
        float  $backoff = 0.5,
        float  $factor  = 2.0
    ): array {
        $attempt = 0;
        $delay   = $backoff;
        do {
            try {
                $options = ['headers' => array_merge($this->globalHeaders, $headers)];
                if ($params) {
                    $options['query']      = $params;
                }
                if ($data) {
                    $options['form_params'] = ['payload' => json_encode($data)];
                }
                $resp = $this->guzzle->request($method, $url, $options);
                return json_decode($resp->getBody()->getContents(), true);
            } catch (RequestException $e) {
                $attempt++;
                if ($attempt >= $retries) {
                    throw $e;
                }
                usleep((int)($delay * 1e6));
                $delay *= $factor;
            }
        } while ($attempt < $retries);

        return ['status' => false, 'code' => 500, 'error' => "{$method} $url failed after $retries attempts"];
    }

    private function buildPath(string $path = ''): string
    {
        $parts = array_filter([
            rtrim($this->baseUrl, '/'),
            $this->dbName,
            $this->tableName,
            ltrim($path, '/')
        ]);
        return implode('/', $parts);
    }

    private function assembleParams(): array
    {
        $p = [];
        if ($this->whereQuery) {
            $p['query_and']  = '[' . implode('|', $this->whereQuery) . ']';
        }
        if ($this->orWhereQuery) {
            $p['query_or']   = '[' . implode('|', $this->orWhereQuery) . ']';
        }
        if ($this->sortByList) {
            $p['sort']       = '[' . implode('|', $this->sortByList) . ']';
        }
        if ($this->groupBy) {
            $p['group_by']   = $this->groupBy;
        }
        if ($this->page > 0) {
            $p['page']       = $this->page;
        }
        if ($this->perPage > 0) {
            $p['per_page']   = $this->perPage;
        }
        if ($this->asPipeline) {
            $p['as_pipeline'] = true;
        }
        return $p;
    }

    private function resetQuery(): void
    {
        $this->whereQuery   = [];
        $this->orWhereQuery = [];
        $this->sortByList   = [];
        $this->groupBy      = null;
        $this->page         = 0;
        $this->perPage      = 0;
        $this->asPipeline   = false;
    }

    private function wrapResponse(array $raw, bool $single = false, bool $utils = false): MongoApiResponse
    {
        if (!($raw['status'] ?? false)) {
            return new MongoApiResponse(['status' => false, 'code' => $raw['code'] ?? 500, 'error' => $raw['error'] ?? 'Unknown error']);
        }
        if ($utils) {
            return new MongoApiResponse($raw);
        }
        $results = $raw['results'] ?? [];
        $payload = [
            'status'     => true,
            'code'       => $raw['code']       ?? 200,
            'database'   => $raw['database']   ?? null,
            'table'      => $raw['table']      ?? null,
            'count'      => $raw['count']      ?? 0,
            'pagination' => $raw['pagination'] ?? [],
            'query'      => $raw['query']      ?? [],
            'data'       => $single ? ($results[0] ?? null) : $results
        ];
        return new MongoApiResponse($payload);
    }

    // — Fluent interface —
    public function fromDb(string $db): self
    {
        $this->dbName = $db;
        return $this;
    }
    public function intoDb(string $db): self
    {
        return $this->fromDb($db);
    }
    public function fromTable(string $t): self
    {
        $this->tableName = $t;
        return $this;
    }
    public function intoTable(string $t): self
    {
        return $this->fromTable($t);
    }
    public function useDb(string $db): self
    {
        return $this->fromDb($db);
    }
    public function useTable(string $t): self
    {
        return $this->fromTable($t);
    }
    public function useCollection(string $c): self
    {
        return $this->fromTable($c);
    }

    public function where(string $col, string $op, $val): self
    {
        if (isset($this->operatorMap[$op])) {
            $this->whereQuery[] = "$col,{$this->operatorMap[$op]},{$this->convertVal($val)}";
        }
        return $this;
    }
    public function orWhere(string $col, string $op, $val): self
    {
        if (isset($this->operatorMap[$op])) {
            $this->orWhereQuery[] = "$col,{$this->operatorMap[$op]},{$this->convertVal($val)}";
        }
        return $this;
    }
    private function convertVal($val): string
    {
        if (is_array($val) && count($val) === 2) {
            return "[{$val[0]}:{$val[1]}]";
        }
        return (string)$val;
    }
    public function sortBy(string $col, string $dir): self
    {
        if (in_array($dir, $this->sortOrder)) {
            $this->sortByList[] = "$col:$dir";
        }
        return $this;
    }
    public function groupBy(string $col): self
    {
        $this->groupBy = $col;
        return $this;
    }
    public function page(int $p): self
    {
        if ($p > 0) $this->page = $p;
        return $this;
    }
    public function perPage(int $n): self
    {
        if ($n > 0) $this->perPage = $n;
        return $this;
    }
    public function limit(int $n): self
    {
        return $this->perPage($n);
    }

    // — CRUD methods —
    public function find(bool $pipeline = false): MongoApiResponse
    {
        $this->asPipeline = $pipeline;
        $params = $this->assembleParams();
        $url    = $this->buildPath('select');
        try {
            $raw = $this->sendRequestWithRetry('GET', $url, $params, [], []);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        $resp = $this->wrapResponse($raw, false, false);
        $this->resetQuery();
        return $resp;
    }

    public function first(): MongoApiResponse
    {
        $this->page = 1;
        $this->perPage = 1;
        $params = $this->assembleParams();
        $url    = $this->buildPath('select');
        try {
            $raw = $this->sendRequestWithRetry('GET', $url, $params, [], []);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        $resp = $this->wrapResponse($raw, true, false);
        $this->resetQuery();
        return $resp;
    }

    public function findById(string $id): MongoApiResponse
    {
        $url = $this->buildPath("get/$id");
        try {
            $raw = $this->sendRequestWithRetry('GET', $url, [], [], []);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        return $this->wrapResponse($raw, true, false);
    }

    public function insert($payload): MongoApiResponse
    {
        $url = $this->buildPath('insert');
        try {
            $raw = $this->sendRequestWithRetry('POST', $url, [], (array)$payload, ['Content-Type' => 'application/x-www-form-urlencoded']);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        return $this->wrapResponse($raw, false, false);
    }

    public function insertIf($payload): MongoApiResponse
    {
        $params = $this->assembleParams();
        $url    = $this->buildPath('insert-if');
        try {
            $raw = $this->sendRequestWithRetry('POST', $url, $params, (array)$payload, ['Content-Type' => 'application/x-www-form-urlencoded']);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        return $this->wrapResponse($raw, false, false);
    }

    public function update($payload): MongoApiResponse
    {
        $params = $this->assembleParams();
        $url    = $this->buildPath('update-where');
        try {
            $raw = $this->sendRequestWithRetry('PUT', $url, $params, (array)$payload, ['Content-Type' => 'application/x-www-form-urlencoded']);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        return $this->wrapResponse($raw, false, false);
    }

    public function updateById(string $id, $payload): MongoApiResponse
    {
        $url = $this->buildPath("update/$id");
        try {
            $raw = $this->sendRequestWithRetry('PUT', $url, [], (array)$payload, ['Content-Type' => 'application/x-www-form-urlencoded']);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        return $this->wrapResponse($raw, false, false);
    }

    public function delete(): MongoApiResponse
    {
        $params = $this->assembleParams();
        $url    = $this->buildPath('delete-where');
        try {
            $raw = $this->sendRequestWithRetry('DELETE', $url, $params, [], []);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        return $this->wrapResponse($raw, false, false);
    }

    public function deleteById(string $id): MongoApiResponse
    {
        $url = $this->buildPath("delete/$id");
        try {
            $raw = $this->sendRequestWithRetry('DELETE', $url, [], [], []);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        return $this->wrapResponse($raw, false, false);
    }

    public function executeCustomQuery($query, bool $aggregate = false): MongoApiResponse
    {
        $this->asPipeline = $aggregate;
        $params = $this->assembleParams();
        $url    = $this->buildPath('custom-query');
        try {
            $raw = $this->sendRequestWithRetry('POST', $url, $params, $query, ['Content-Type' => 'application/x-www-form-urlencoded']);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        return $this->wrapResponse($raw, false, false);
    }

    // — Utility endpoints —
    public function listDatabases(): MongoApiResponse
    {
        $url = rtrim($this->baseUrl, '/') . '/databases';
        try {
            $raw = $this->sendRequestWithRetry('GET', $url, [], [], []);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        return $this->wrapResponse($raw, false, true);
    }

    public function listTablesInDb(string $db): MongoApiResponse
    {
        $url = "$this->baseUrl/$db/tables";
        try {
            $raw = $this->sendRequestWithRetry('GET', $url, [], [], []);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        return $this->wrapResponse($raw, false, true);
    }

    public function deleteDatabase(string $db): MongoApiResponse
    {
        $url = "$this->baseUrl/$db/delete";
        try {
            $raw = $this->sendRequestWithRetry('DELETE', $url, [], [], []);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        return $this->wrapResponse($raw, false, true);
    }

    public function deleteTable(string $db, string $tbl): MongoApiResponse
    {
        $url = "$this->baseUrl/$db/$tbl/delete";
        try {
            $raw = $this->sendRequestWithRetry('DELETE', $url, [], [], []);
        } catch (RequestException $e) {
            $raw = ['status' => false, 'code' => 500, 'error' => $e->getMessage()];
        }
        return $this->wrapResponse($raw, false, true);
    }

    // — Aliases —
    public function select(bool $pipeline = false): MongoApiResponse
    {
        return $this->find($pipeline);
    }
    public function all(): MongoApiResponse
    {
        return $this->find(false);
    }
    public function get(): MongoApiResponse
    {
        return $this->find(false);
    }
    public function firstOrNone(): MongoApiResponse
    {
        return $this->first();
    }
    public function one(): MongoApiResponse
    {
        return $this->first();
    }
    public function dropDatabase(string $db): MongoApiResponse
    {
        return $this->deleteDatabase($db);
    }
    public function dropCollection(string $db, string $tbl): MongoApiResponse
    {
        return $this->deleteTable($db, $tbl);
    }
}
