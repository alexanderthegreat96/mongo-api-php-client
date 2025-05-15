<?php

declare(strict_types=1);

namespace Alexanderthegreat96\MongoApiClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MongoApiClient
{
    private Client      $guzzle;
    private string      $baseUrl;
    private array       $globalHeaders;
    private ?string     $dbName         = null;
    private ?string     $tableName      = null;
    private array       $whereQuery     = [];
    private array       $orWhereQuery   = [];
    private array       $sortByList     = [];
    private ?string     $groupBy        = null;
    private int         $page           = 0;
    private int         $perPage        = 0;
    private bool        $asPipeline     = false;
    private bool        $autoConvert    = true;
    private float       $timeout        = 5.0;

    public const ASC = "asc";
    public const DESC = "desc";
    private array $operatorMap = [
        '='         => '=',
        '!='        => '!=',
        '<'         => '<',
        '<='        => '<=',
        '>'         => '>',
        '>='        => '>=',
        'like'      => 'ilike',
        'not_like'  => 'not_like',
        'between'   => 'between',
    ];

    private array $sortOrders = ['asc', 'desc'];

    public function __construct(
        string $serverUrl,
        int    $serverPort,
        string $apiKey           = '',
        string $scheme           = 'http',
        bool   $autoConvertVals  = true,
        float  $timeoutSeconds   = 5.0
    ) {
        $this->baseUrl       = sprintf('%s://%s:%d/db', $scheme, $serverUrl, $serverPort);
        $this->globalHeaders = [
            'Accept'    => 'application/json',
            'api_key'   => $apiKey,
        ];
        $this->autoConvert   = $autoConvertVals;
        $this->timeout       = $timeoutSeconds;
        $this->guzzle        = new Client(['timeout' => $timeoutSeconds]);
    }

    private function sendRequestWithRetry(
        string $method,
        string $url,
        array  $params    = [],
        array  $data      = [],
        array  $headers   = [],
        int    $retries   = 3,
        float  $backoff   = 0.5,
        float  $factor    = 2.0
    ): array {
        $attempt = 0;
        $delay   = $backoff;

        do {
            try {
                $options = [
                    'headers' => array_merge($this->globalHeaders, $headers),
                ];
                if ($params) {
                    $options['query'] = $params;
                }
                if ($data) {
                    // always x-www-form-urlencoded with key 'payload'
                    $options['form_params'] = [
                        'payload' => json_encode($data),
                    ];
                }

                $resp = $this->guzzle->request($method, $url, $options);
                return json_decode((string)$resp->getBody(), true);
            } catch (RequestException $e) {
                $attempt++;
                if ($attempt >= $retries) {
                    // out of retries
                    return [
                        'status' => false,
                        'code'   => $e->getCode() ?: 500,
                        'error'  => $e->getMessage(),
                    ];
                }
                // sleep then back off
                usleep((int)($delay * 1e6));
                $delay *= $factor;
            }
        } while ($attempt < $retries);

        // should not reach here
        return ['status' => false, 'code' => 500, 'error' => "{$method} {$url} failed"];
    }

    private function buildPath(string $path = ''): string
    {
        $parts = array_filter([
            rtrim($this->baseUrl, '/'),
            $this->dbName,
            $this->tableName,
            ltrim($path, '/'),
        ]);

        return implode('/', $parts);
    }

    private function assembleParams(): array
    {
        $p = [];

        if ($this->whereQuery) {
            $p['query_and'] = '[' . implode('|', $this->whereQuery) . ']';
        }
        if ($this->orWhereQuery) {
            $p['query_or'] = '[' . implode('|', $this->orWhereQuery) . ']';
        }
        if ($this->sortByList) {
            $p['sort'] = '[' . implode('|', $this->sortByList) . ']';
        }
        if ($this->groupBy) {
            $p['group_by'] = $this->groupBy;
        }
        if ($this->page > 0) {
            $p['page'] = $this->page;
        }
        if ($this->perPage > 0) {
            $p['per_page'] = $this->perPage;
        }
        if ($this->asPipeline) {
            $p['as_pipeline'] = true;
        }
        if ($this->autoConvert) {
            $p['auto_convert_inputs'] = true;
        }

        return $p;
    }

    private function resetQuery(): void
    {
        $this->whereQuery    = [];
        $this->orWhereQuery  = [];
        $this->sortByList    = [];
        $this->groupBy       = null;
        $this->page          = 0;
        $this->perPage       = 0;
        $this->asPipeline    = false;
    }

    private function tagValue($val, bool $autoConvert): string
    {
        $suffix = $autoConvert ? '/a' : '/n';
        return (string)$val . $suffix;
    }

    private function convertVal($val, bool $autoConvert = false): string
    {
        if (is_array($val) && count($val) === 2) {
            return '[' . $this->tagValue($val[0], $autoConvert) . ':' . $this->tagValue($val[1], $autoConvert) . ']';
        }
        return $this->tagValue($val, $autoConvert);
    }

    private function wrapResponse(array $raw, bool $single = false, bool $utils = false): MongoApiResponse
    {
        // failure
        if (!($raw['status'] ?? false)) {
            return new MongoApiResponse([
                'status' => false,
                'code'   => $raw['code']  ?? 500,
                'error'  => $raw['error'] ?? 'Unknown error',
            ]);
        }

        // utils (databases/tables/message)
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
            'data'       => $single
                ? ($results[0] ?? null)
                : $results,
        ];

        return new MongoApiResponse($payload);
    }

    // — Fluent interface —
    public function fromDb(string $db): self       { $this->dbName = $db; return $this; }
    public function intoDb(string $db): self       { return $this->fromDb($db); }
    public function fromTable(string $t): self     { $this->tableName = $t; return $this; }
    public function intoTable(string $t): self     { return $this->fromTable($t); }
    public function useDb(string $db): self        { return $this->fromDb($db); }
    public function useTable(string $t): self      { return $this->fromTable($t); }
    public function useCollection(string $c): self { return $this->fromTable($c); }

    public function where(string $col, string $op, $val, bool $autoConvert = true): self
    {
        if (isset($this->operatorMap[$op])) {
            $this->whereQuery[] = sprintf('%s,%s,%s',
                $col,
                $this->operatorMap[$op],
                $this->convertVal($val, $autoConvert)
            );
        }
        return $this;
    }

    public function orWhere(string $col, string $op, $val, bool $autoConvert = True): self
    {
        if (isset($this->operatorMap[$op])) {
            $this->orWhereQuery[] = sprintf('%s,%s,%s',
                $col,
                $this->operatorMap[$op],
                $this->convertVal($val, $autoConvert)
            );
        }
        return $this;
    }

    public function sortBy(string $col, string $dir): self
    {
        if (in_array($dir, $this->sortOrders, true)) {
            $this->sortByList[] = "$col:$dir";
        }
        return $this;
    }

    public function groupBy(string $col): self { $this->groupBy = $col; return $this; }
    public function page(int $p): self         { if ($p>0) $this->page = $p; return $this; }
    public function perPage(int $n): self      { if ($n>0) $this->perPage = $n; return $this; }
    public function limit(int $n): self        { return $this->perPage($n); }

    // — CRUD —

    public function find(bool $pipeline = false): MongoApiResponse
    {
        $this->asPipeline = $pipeline;
        $raw = $this->sendRequestWithRetry(
            'GET',
            $this->buildPath('select'),
            $this->assembleParams(),
            [],
            []
        );
        $resp = $this->wrapResponse($raw, false, false);
        $this->resetQuery();
        return $resp;
    }

    public function first(): MongoApiResponse
    {
        $this->page = 1;
        $this->perPage = 1;
        $raw = $this->sendRequestWithRetry(
            'GET',
            $this->buildPath('select'),
            $this->assembleParams(),
            [],
            []
        );
        $resp = $this->wrapResponse($raw, true, false);
        $this->resetQuery();
        return $resp;
    }

    public function findById(string $id): MongoApiResponse
    {
        $raw = $this->sendRequestWithRetry(
            'GET',
            $this->buildPath("get/$id"),
            [],
            [],
            []
        );
        return $this->wrapResponse($raw, true, false);
    }

    public function insert($payload): MongoApiResponse
    {
        $raw = $this->sendRequestWithRetry(
            'POST',
            $this->buildPath('insert'),
            [],
            (array)$payload,
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );
        return $this->wrapResponse($raw, false, false);
    }

    public function insertIf($payload): MongoApiResponse
    {
        $raw = $this->sendRequestWithRetry(
            'POST',
            $this->buildPath('insert-if'),
            $this->assembleParams(),
            (array)$payload,
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );
        return $this->wrapResponse($raw, false, false);
    }

    public function update($payload): MongoApiResponse
    {
        $raw = $this->sendRequestWithRetry(
            'PUT',
            $this->buildPath('update-where'),
            $this->assembleParams(),
            (array)$payload,
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );
        return $this->wrapResponse($raw, false, false);
    }

    public function updateById(string $id, $payload): MongoApiResponse
    {
        $raw = $this->sendRequestWithRetry(
            'PUT',
            $this->buildPath("update/$id"),
            [],
            (array)$payload,
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );
        return $this->wrapResponse($raw, false, false);
    }

    public function delete(): MongoApiResponse
    {
        $raw = $this->sendRequestWithRetry(
            'DELETE',
            $this->buildPath('delete-where'),
            $this->assembleParams(),
            [],
            []
        );
        return $this->wrapResponse($raw, false, false);
    }

    public function deleteById(string $id): MongoApiResponse
    {
        $raw = $this->sendRequestWithRetry(
            'DELETE',
            $this->buildPath("delete/$id"),
            [],
            [],
            []
        );
        return $this->wrapResponse($raw, false, false);
    }

    public function executeCustomQuery($query, bool $aggregate = false): MongoApiResponse
    {
        $this->asPipeline = $aggregate;
        $raw = $this->sendRequestWithRetry(
            'POST',
            $this->buildPath('custom-query'),
            $this->assembleParams(),
            $query,
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );
        return $this->wrapResponse($raw, false, false);
    }

    // — Utilities —

    public function listDatabases(): MongoApiResponse
    {
        $raw = $this->sendRequestWithRetry(
            'GET',
            rtrim($this->baseUrl, '/') . '/databases'
        );
        return $this->wrapResponse($raw, false, true);
    }

    public function listTablesInDb(string $db): MongoApiResponse
    {
        $raw = $this->sendRequestWithRetry(
            'GET',
            "{$this->baseUrl}/$db/tables"
        );
        return $this->wrapResponse($raw, false, true);
    }

    public function deleteDatabase(string $db): MongoApiResponse
    {
        $raw = $this->sendRequestWithRetry(
            'DELETE',
            "{$this->baseUrl}/$db/delete"
        );
        return $this->wrapResponse($raw, false, true);
    }

    public function deleteTable(string $db, string $tbl): MongoApiResponse
    {
        $raw = $this->sendRequestWithRetry(
            'DELETE',
            "{$this->baseUrl}/$db/$tbl/delete"
        );
        return $this->wrapResponse($raw, false, true);
    }

    // — Aliases —

    public function select(bool $pipeline = false): MongoApiResponse   { return $this->find($pipeline); }
    public function all(): MongoApiResponse                           { return $this->find(false); }
    public function get(): MongoApiResponse                           { return $this->find(false); }
    public function firstOrNone(): MongoApiResponse                   { return $this->first(); }
    public function one(): MongoApiResponse                           { return $this->first(); }
    public function dropDatabase(string $db): MongoApiResponse        { return $this->deleteDatabase($db); }
    public function dropCollection(string $db, string $tbl): MongoApiResponse { return $this->deleteTable($db, $tbl); }
}
