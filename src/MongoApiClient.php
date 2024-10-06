<?php

namespace Alexanderthegreat96\MongoApiClient;

use GuzzleHttp\Client;

class MongoApiClient
{
    /**
     * headers applied across all requests
     * @var array
     */
    private $globalHeaders;

    /**
     * the server url
     *
     * @var string
     */
    private $serverUrl;

    /**
     * the server port
     *
     * @var string
     */
    private $serverPort;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * the api url
     *
     * @var int
     */
    private $apiUrl;

    /**
     * database name
     *
     * @var string
     */
    private $dbName;

    /**
     * table / collection name
     *
     * @var string
     */
    private $tableName;

    /**
     * results per page
     *
     * @var int
     */
    private $perPage;

    /**
     * group records by / aggregate
     *
     * @var string
     */
    private $groupBy;

    /**
     * current page
     *
     * @var int
     */
    private $page;

    /**
     * query params
     *
     * @var array
     */
    private $queryParams;

    /**
     * where_query
     *
     * @var array
     */
    private $whereQuery;

    /**
     * or where query
     *
     * @var array
     */
    private $orWhereQuery;

    /**
     * sort by
     *
     * @var array
     */
    private $sortByList;

    /**
     * operator map for queries
     *
     * @var array
     */
    private $operatorMap;

    /**
     * sorting order
     *
     * @var array
     */
    private $sortOrder;

    /**
     * Guzzle HTTP Client
     *
     * @var \GuzzleHttp\Client
     */
    private $guzzle;

    /**
     * The last query's results
     *
     * @var [array | null]
     */
    private $queryResults;

    /**
     * Basic Constructor
     *
     * @param [string] $serverUrl
     * @param integer $serverPort
     * @param string $scheme
     * @param string $apiKey
     */
    public function __construct($serverUrl = null, $serverPort = 0, $scheme = "http", string $apiKey = null)
    {
        $this->globalHeaders = [
            'accept' => 'application/json',
            'api_key' => $apiKey
        ];

        $this->serverUrl = $serverUrl;
        $this->serverPort = $serverPort;
        $this->apiKey = $apiKey;

        $this->apiUrl = $scheme . "://" . $serverUrl . ":" . strval($serverPort);

        $this->dbName = "my-db";
        $this->tableName = "my-collection";
        $this->perPage = 0;
        $this->page = 0;

        $this->queryParams = array();

        $this->whereQuery = array();
        $this->orWhereQuery = array();
        $this->sortByList = array();

        $this->operatorMap = array(
            "=" => "=",
            "!=" => "!=",
            "<" => "<",
            "<=" => "<=",
            ">" => ">",
            ">=" => ">=",
            "like" => "ilike",
            "not_like" => "not_like",
            "between" => "between"

        );

        $this->sortOrder = array("asc", "desc");

        $this->guzzle = new Client();

        $this->queryResults = null;
    }

    /**
     * Builds the query
     * before sending it to the server
     *
     * @return MongoApiClient
     */
    private function assembleQuery(): MongoApiClient
    {
        if (count($this->whereQuery) > 0) {

            $this->queryParams["query_and"] = "[" . implode("|", $this->whereQuery) . "]";
        }

        if (count($this->orWhereQuery) > 0) {
            $this->queryParams["query_or"] = "[" . implode("|", $this->orWhereQuery) . "]";
        }

        if ($this->perPage > 0) {
            $this->queryParams["per_page"] = $this->perPage;
        }

        if ($this->page > 0) {
            $this->queryParams["page"] = $this->page;
        }

        if (count($this->sortByList) > 0) {
            $this->queryParams["sort"] = "[" . implode("|", $this->sortByList) . "]";
        }

        if (!is_null($this->groupBy) || !empty($this->groupBy)) {
            $this->queryParams["group_by"] = $this->groupBy;
        }

        return $this;
    }

    /**
     * Makes HTTP requests
     * to the server
     *
     * @param string $url
     * @param string $method
     * @param boolean $queryParams
     * @param array|null $data
     * @param array $headers
     * @return array
     */
    private function makeRequest(
        string $url,
        string $method,
        bool $queryParams = false,
        array $data = null,
        array $headers = []
    ): array {
        $params = [];

        if ($queryParams) {
            $params["query"] = $this->queryParams;
        }

        $params["headers"] = array_merge($headers, $this->globalHeaders);

        if ($data) {
            $params["form_params"] = [
                "payload" => json_encode($data)
            ];
        }

        try {
            $response = $this->guzzle->request($method, $url, $params);
            return json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            return [
                'status' => false,
                'error' => "Internal Server Error (500): " . $e->getMessage()
            ];
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            return [
                'status' => false,
                'error' => "Error: Server not responding, due to: " . $e->getMessage()
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return json_decode($e->getResponse()->getBody()->getContents(), true);
        }
    }

    /**
     * Lists all databases
     * on the current server
     *
     * @return array
     */
    public function listDatabases(): array
    {
        $requestUrl = $this->apiUrl . "/db/databases";
        return $this->makeRequest($requestUrl, "GET", false, null);
    }

    /**
     * Lists all tables inside a database
     *
     * @param string|null $dbName
     * @return array
     */
    public function listTablesInDb(string $dbName = null): array
    {
        if (!$dbName) {
            return array(
                "status" => false,
                "error" => "You did not provide a database name"
            );
        }

        $requestUrl = $this->apiUrl . "/db/" . $dbName . "/tables";
        return $this->makeRequest($requestUrl, "GET", false, null);
    }

    /**
     * sets the database name
     *
     * @param [type] $dbName
     * @return MongoApiClient
     */
    public function fromDb($dbName = null): MongoApiClient
    {
        if ($dbName) {
            $this->dbName = $dbName;
        }
        return $this;
    }

    /**
     * Sets the database name
     *
     * @param [type] $dbName
     * @return MongoApiClient
     */
    public function intoDb($dbName = null): MongoApiClient
    {
        if ($dbName) {
            $this->dbName = $dbName;
        }
        return $this;
    }

    /**
     * sets the table / collection
     *
     * @param [type] $tableName
     * @return MongoApiClient
     */
    public function fromTable($tableName = null): MongoApiClient
    {
        if ($tableName) {
            $this->tableName = $tableName;
        }
        return $this;
    }

    /**
     * sets the table / collection
     *
     * @param [type] $tableName
     * @return MongoApiClient
     */
    public function intoTable($tableName = null): MongoApiClient
    {
        if ($tableName) {
            $this->tableName = $tableName;
        }
        return $this;
    }

    /**
     * Where constraint
     *
     * @param [type] $colName
     * @param [type] $operator
     * @param [type] $colVal
     * @return MongoApiClient
     */
    public function where($colName = null, $operator = null, $colVal = null): MongoApiClient
    {
        if (array_key_exists($operator, $this->operatorMap)) {
            $this->whereQuery[] = $colName . "," . $this->operatorMap[$operator] . "," . $this->convertColValueForArrays($colVal);
        }
        return $this;
    }

    /**
     * Converts an array input for column value
     * to a structure like this [val1 : val2]
     * ex: for when using "between" operator
     *
     * @param $colVal
     * @return string
     */
    private function convertColValueForArrays($colVal = null)
    {
        if (is_array($colVal)) {
            if (sizeof($colVal) == 2) {
                $first = $colVal[0];
                $last = $colVal[1];
                return "[" . $first . ":" . $last .  "]";
            }
        }

        return strval($colVal);
    }

    /**
     * orWhere constraint
     *
     * @param [type] $colName
     * @param [type] $operator
     * @param [type] $colVal
     * @return MongoApiClient
     */
    public function orWhere($colName = null, $operator = null, $colVal = null): MongoApiClient
    {
        if (array_key_exists($operator, $this->operatorMap)) {
            $this->orWhereQuery[] = $colName . "," . $this->operatorMap[$operator] . "," . $this->convertColValueForArrays($colVal);
        }
        return $this;
    }

    /**
     * Sets how many results 
     * per page
     *
     * @param integer $perPage
     * @return MongoApiClient
     */
    public function perPage($perPage = 0): MongoApiClient
    {
        if ($perPage > 0) {
            $this->perPage = $perPage;
        }
        return $this;
    }

    /**
     * Sets the current page
     *
     * @param integer $page
     * @return MongoApiClient
     */
    public function page($page = 0): MongoApiClient
    {
        if ($page > 0) {
            $this->page = $page;
        }
        return $this;
    }

    /**
     * Sets the orderBy
     *
     * @param [type] $colName
     * @param [type] $direction
     * @return MongoApiClient
     */
    public function sortBy($colName = null, $direction = null): MongoApiClient
    {
        if (in_array($direction, $this->sortOrder)) {
            $this->sortByList[] = $colName . ":" . $direction;
        }
        return $this;
    }

    public function groupBy($colName = null): MongoApiClient
    {
        $this->groupBy = $colName;
        return $this;
    }

    /**
     * Gets the query results
     * and pushes them into 
     * $this->queryResults
     *
     * @return MongoApiClient
     */
    public function get(): MongoApiClient
    {
        $results = $this->find();
        if ($results['status']) {
            if ($results['count'] > 0) {
                $this->queryResults = $results;
            }
        }

        return $this;
    }
    /**
     * Retrieves or multiple records
     * based on a provided query
     *
     * @return array
     */
    public function select(): array
    {
        $this->assembleQuery();
        $requestUrl = $this->apiUrl . "/db/" . $this->dbName . "/" . $this->tableName . "/select";

        return $this->makeRequest($requestUrl, "GET", true, null);
    }

    /**
     * Does what select does
     *
     * @return array
     */
    public function find(): array
    {
        return $this->select();
    }

    /**
     * Does what selectById does
     *
     * @param string|null $mongoId
     * @return array
     */
    public function findById(string $mongoId = null): array
    {
        return $this->selectById($mongoId);
    }

    /**
     * Returns result count
     *
     * @return array
     */
    public function count(): array
    {
        if (is_null($this->queryResults)) {
            $results = $this->select();
            if (!$results['status']) {
                return ['status' => false, 'error' => $results['error']];
            }
            return ['status' => true, 'count' => $results['count']];
        }
        return [
            'status' => true,
            'count' => is_array($this->queryResults)
                ? (array_key_exists('count', $this->queryResults)
                    ? $this->queryResults['count']
                    : count($this->queryResults))
                : $this->queryResults['count']
        ];
    }

    /**
     * Returns the first result from the last query
     *
     * @return array
     */
    public function first(): array
    {
        if (is_null($this->queryResults)) {
            return ['status' => false, 'error' => 'Query did not return any data. Are you sure you provided the .get() method before this?'];
        }

        if (isset($this->queryResults['results'])) {
            return ['status' => true, 'result' => $this->queryResults['results'][0]];
        }

        return ['status' => true, 'result' => $this->queryResults];
    }
    /**
     * Returns a record by mongoId
     *
     * @param string|null $mongoId
     * @return array
     */
    public function selectById(string $mongoId = null): array
    {
        if (!$mongoId) {
            return array(
                "status" => false,
                "error" => "You failed to provide a Mongo record ID."
            );
        }

        $requestUrl = $this->apiUrl . "/db/" . $this->dbName . "/" . $this->tableName . "/get/" . $mongoId;
        return $this->makeRequest($requestUrl, "GET", false, null);
    }

    /**
     * Performs an update for one
     * or multiple records based
     * on certain conditions set through
     * the provided query
     *
     * @param array|null $data
     * @return array
     */
    public function update(array $data = null): array
    {

        if (!$data) {
            return array(
                "status" => false,
                "error" => "You failed to provide some data to send to the server"
            );
        }
        $this->assembleQuery();

        $requestUrl = $this->apiUrl . "/db/" . $this->dbName . "/" . $this->tableName . "/update-where";

        return $this->makeRequest($requestUrl, "PUT", true, $data, [
            "Content-Type" => "application/x-www-form-urlencoded"
        ]);
    }

    /**
     * Updates an existing record
     * by mongoId
     *
     * @param string|null $mongoId
     * @param array|null $data
     * @return array
     */
    public function updateById(string $mongoId = null, array $data = null): array
    {
        if (!$data && !$mongoId) {
            return array(
                "status" => false,
                "error" => "You failed to provide some data + the mongoId to send to the server"
            );
        }

        $requestUrl = $this->apiUrl . "/db/" . $this->dbName . "/" . $this->tableName . "/update/" . $mongoId;

        return $this->makeRequest($requestUrl, "PUT", false, $data, [
            "Content-Type" => "application/x-www-form-urlencoded"
        ]);
    }

    /**
     * Inserts a new record
     *
     * @param array|null $data
     * @return array
     */
    public function insert(array $data = null): array
    {
        if (!$data) {
            return array(
                "status" => false,
                "error" => "You failed to provide some data to send to the server"
            );
        }

        $requestUrl = $this->apiUrl . "/db/" . $this->dbName . "/" . $this->tableName . "/insert";

        return $this->makeRequest($requestUrl, "POST", false, $data, [
            "Content-Type" => "application/x-www-form-urlencoded"
        ]);
    }

    /**
     * Inserts a record if some
     * condition is met
     * use the query builder before 
     * running this method
     *
     * @param array|null $data
     * @return array
     */
    public function insertIf(array $data = null): array
    {
        if (!$data) {
            return array(
                "status" => false,
                "error" => "You failed to provide some data to send to the server"
            );
        }

        $this->assembleQuery();

        $requestUrl = $this->apiUrl . "/db/" . $this->dbName . "/" . $this->tableName . "/insert-if";

        return $this->makeRequest($requestUrl, "POST", true, $data, [
            "Content-Type" => "application/x-www-form-urlencoded"
        ]);
    }

    /**
     * Deletes records based on query
     * a query should be provided
     *
     * @return array
     */
    public function delete(): array
    {
        $this->assembleQuery();

        $requestUrl = $this->apiUrl . "/db/" . $this->dbName . "/" . $this->tableName . "/delete-where";
        return $this->makeRequest($requestUrl, "DELETE", true, null);
    }

    /**
     * Deletes a record by ID
     *
     * @param string|null $mongoId
     * @return array
     */
    public function deleteById(string $mongoId = null): array
    {
        if (!$mongoId) {
            return [
                "status" => false,
                "error" => "You failed to provide a mongoId to send to the server."
            ];
        }

        $requestUrl = $this->apiUrl . "/db/" . $this->dbName . "/" . $this->tableName . "/delete/" . $mongoId;
        return $this->makeRequest($requestUrl, "DELETE", false, null);
    }

    /**
     * Deletes a database
     *
     * @param string|null $dbName
     * @return array
     */
    public function deleteDatabase(string $dbName = null): array
    {
        if (!$dbName) {
            return [
                "status" => false,
                "error" => "You did not provide a database name"
            ];
        }

        $requestUrl = $this->apiUrl . "/db/" . $dbName . "/delete";
        return $this->makeRequest($requestUrl, "DELETE", false, null);
    }

    /**
     * Deletes Tables inside a database
     *
     * @param [type] $dbName
     * @param [type] $tableName
     * @return void
     */
    public function deleteTablesInDatabase($dbName = null, $tableName = null): array
    {
        if (!$tableName && !$dbName) {
            return [
                "status" => false,
                "error" => "You did not provide a valid database + table / collection name."
            ];
        }

        $requestUrl = $this->apiUrl . "/db/" . $dbName . "/" . $tableName . "/delete";
        return $this->makeRequest($requestUrl, "DELETE", false, null);
    }
}
