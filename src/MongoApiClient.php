<?php

namespace Alexanderthegreat96\MongoApiClient;

use GuzzleHttp\Client;

class MongoApiClient
{
    /**
     * the server url
     *
     * @var string
     */
    private $server_url;
    /**
     * the server port
     *
     * @var string
     */
    private $server_port;
    /**
     * the api url
     *
     * @var int
     */
    private $api_url;
    /**
     * database name
     *
     * @var string
     */
    private $db_name;
    /**
     * table / collection name
     *
     * @var string
     */
    private $table_name;
    /**
     * results per page
     *
     * @var int
     */
    private $per_page;
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
    private $query_params;
    /**
     * where_query
     *
     * @var array
     */
    private $where_query;
    /**
     * or where query
     *
     * @var array
     */
    private $or_where_query;
    /**
     * sort by
     *
     * @var array
     */
    private $sort_by_list;
    /**
     * operator map for queries
     *
     * @var array
     */
    private $operator_map;
    /**
     * sorting order
     *
     * @var array
     */
    private $sort_order;
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
    private $query_results;

    /**
     * Basic Constructor
     *
     * @param [string] $server_url
     * @param integer $server_port
     * @param string $scheme
     */
    public function __construct($server_url = null, $server_port = 0, $scheme = "http")
    {
        $this->server_url = $server_url;
        $this->server_port = $server_port;
        $this->api_url = $scheme . "://" . $server_url . ":" . strval($server_port);

        $this->db_name = "my-db";
        $this->table_name = "my-collection";
        $this->per_page = 0;
        $this->page = 0;

        $this->query_params = array();

        $this->where_query = array();
        $this->or_where_query = array();
        $this->sort_by_list = array();

        $this->operator_map = array(
            "=" => "=",
            "!=" => "!=",
            "<" => "<",
            "<=" => "<=",
            ">" => ">",
            ">=" => ">=",
            "like" => "_like_"
        );

        $this->sort_order = array("asc", "desc");

        $this->guzzle = new Client();

        $this->query_results = null;
    }

    /**
     * Builds the query
     * before sending it to the server
     *
     * @return MongoApiClient
     */
    private function assembleQuery(): MongoApiClient
    {
        if (count($this->where_query) > 0) {

            $this->query_params["query_and"] = "[" . implode("|", $this->where_query) . "]";
        }

        if (count($this->or_where_query) > 0) {
            $this->query_params["query_or"] = "[" . implode("|", $this->or_where_query) . "]";
        }

        if ($this->per_page > 0) {
            $this->query_params["per_page"] = $this->per_page;
        }

        if ($this->page > 0) {
            $this->query_params["page"] = $this->page;
        }

        if (count($this->sort_by_list) > 0) {
            $this->query_params["sort"] = "[" . implode("|", $this->sort_by_list) . "]";
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
            $params["query"] = $this->query_params;
        }

        $params["headers"] = $headers;

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
        $request_url = $this->api_url . "/db/databases";
        return $this->makeRequest($request_url, "GET", false, null, [
            'accept' => 'application/json'
        ]);
    }

    /**
     * Lists all tables inside a database
     *
     * @param string|null $db_name
     * @return array
     */
    public function listTablesInDb(string $db_name = null): array
    {
        if (!$db_name) {
            return array(
                "status" => false,
                "error" => "You did not provide a database name"
            );
        }

        $request_url = $this->api_url . "/db/" . $db_name . "/tables";
        return $this->makeRequest($request_url, "GET", false, null, [
            'accept' => 'application/json'
        ]);
    }

    /**
     * sets the database name
     *
     * @param [type] $db_name
     * @return MongoApiClient
     */
    public function fromDb($db_name = null): MongoApiClient
    {
        if ($db_name) {
            $this->db_name = $db_name;
        }
        return $this;
    }

    /**
     * Sets the database name
     *
     * @param [type] $db_name
     * @return MongoApiClient
     */
    public function intoDb($db_name = null): MongoApiClient
    {
        if ($db_name) {
            $this->db_name = $db_name;
        }
        return $this;
    }

    /**
     * sets the table / collection
     *
     * @param [type] $table_name
     * @return MongoApiClient
     */
    public function fromTable($table_name = null): MongoApiClient
    {
        if ($table_name) {
            $this->table_name = $table_name;
        }
        return $this;
    }

    /**
     * sets the table / collection
     *
     * @param [type] $table_name
     * @return MongoApiClient
     */
    public function intoTable($table_name = null): MongoApiClient
    {
        if ($table_name) {
            $this->table_name = $table_name;
        }
        return $this;
    }

    /**
     * Where constraint
     *
     * @param [type] $col_name
     * @param [type] $operator
     * @param [type] $col_val
     * @return MongoApiClient
     */
    public function where($col_name = null, $operator = null, $col_val = null): MongoApiClient
    {
        if (array_key_exists($operator, $this->operator_map)) {
            $this->where_query[] = $col_name . "," . $this->operator_map[$operator] . "," . strval($col_val);
        }
        return $this;
    }

    /**
     * orWhere constraint
     *
     * @param [type] $col_name
     * @param [type] $operator
     * @param [type] $col_val
     * @return MongoApiClient
     */
    public function orWhere($col_name = null, $operator = null, $col_val = null): MongoApiClient
    {
        if (array_key_exists($operator, $this->operator_map)) {
            $this->or_where_query[] = $col_name . "," . $this->operator_map[$operator] . "," . strval($col_val);
        }
        return $this;
    }

    /**
     * Sets how many results 
     * per page
     *
     * @param integer $per_page
     * @return MongoApiClient
     */
    public function perPage($per_page = 0): MongoApiClient
    {
        if ($per_page > 0) {
            $this->per_page = $per_page;
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
     * @param [type] $col_name
     * @param [type] $direction
     * @return MongoApiClient
     */
    public function sortBy($col_name = null, $direction = null): MongoApiClient
    {
        if (in_array($direction, $this->sort_order)) {
            $this->sort_by_list[] = $col_name . ":" . $direction;
        }
        return $this;
    }

    /**
     * Gets the query results
     * and pushes them into 
     * $this->query_results
     *
     * @return MongoApiClient
     */
    public function get(): MongoApiClient
    {
        $results = $this->find();
        if ($results['status']) {
            if ($results['count'] > 0) {
                $this->query_results = $results;
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
        $request_url = $this->api_url . "/db/" . $this->db_name . "/" . $this->table_name . "/select";

        return $this->makeRequest($request_url, "GET", true, null, [
            "accept: application/json"
        ]);
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
     * @param string|null $mongo_id
     * @return array
     */
    public function findById(string $mongo_id = null): array
    {
        return $this->selectById($mongo_id);
    }

    /**
     * Returns result count
     *
     * @return array
     */
    public function count(): array
    {
        if (is_null($this->query_results)) {
            $results = $this->select();
            if (!$results['status']) {
                return ['status' => false, 'error' => $results['error']];
            }
            return ['status' => true, 'count' => $results['count']];
        }
        return [
            'status' => true,
            'count' => is_array($this->query_results)
                ? (array_key_exists('count', $this->query_results)
                    ? $this->query_results['count']
                    : count($this->query_results))
                : $this->query_results['count']
        ];
    }

    /**
     * Returns the first result from the last query
     *
     * @return array
     */
    public function first(): array
    {
        if (is_null($this->query_results)) {
            return ['status' => false, 'error' => 'Query did not return any data. Are you sure you provided the .get() method before this?'];
        }

        if (isset($this->query_results['results'])) {
            return ['status' => true, 'result' => $this->query_results['results'][0]];
        }

        return ['status' => true, 'result' => $this->query_results];
    }
    /**
     * Returns a record by mongo_id
     *
     * @param string|null $mongo_id
     * @return array
     */
    public function selectById(string $mongo_id = null): array
    {
        if (!$mongo_id) {
            return array(
                "status" => false,
                "error" => "You failed to provide a Mongo record ID."
            );
        }

        $request_url = $this->api_url . "/db/" . $this->db_name . "/" . $this->table_name . "/get/" . $mongo_id;
        return $this->makeRequest($request_url, "GET", false, null, ["accept" => "application/json"]);
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

        $request_url = $this->api_url . "/db/" . $this->db_name . "/" . $this->table_name . "/update-where";

        return $this->makeRequest($request_url, "PUT", true, $data, [
            "Content-Type" => "application/x-www-form-urlencoded"
        ]);
    }

    /**
     * Updates an existing record
     * by mongo_id
     *
     * @param string|null $mongo_id
     * @param array|null $data
     * @return array
     */
    public function updateById(string $mongo_id = null, array $data = null): array
    {
        if (!$data && !$mongo_id) {
            return array(
                "status" => false,
                "error" => "You failed to provide some data + the mongo_id to send to the server"
            );
        }

        $request_url = $this->api_url . "/db/" . $this->db_name . "/" . $this->table_name . "/update/" . $mongo_id;

        return $this->makeRequest($request_url, "PUT", false, $data, [
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

        $request_url = $this->api_url . "/db/" . $this->db_name . "/" . $this->table_name . "/insert";

        return $this->makeRequest($request_url, "POST", false, $data, [
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

        $request_url = $this->api_url . "/db/" . $this->db_name . "/" . $this->table_name . "/insert-if";

        return $this->makeRequest($request_url, "POST", true, $data, [
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

        $request_url = $this->api_url . "/db/" . $this->db_name . "/" . $this->table_name . "/delete-where";
        return $this->makeRequest($request_url, "DELETE", true, null, [
            'accept' => 'application/json'
        ]);
    }

    /**
     * Deletes a record by ID
     *
     * @param string|null $mongo_id
     * @return array
     */
    public function deleteById(string $mongo_id = null): array
    {
        if (!$mongo_id) {
            return [
                "status" => false,
                "error" => "You failed to provide a mongo_id to send to the server."
            ];
        }

        $request_url = $this->api_url . "/db/" . $this->db_name . "/" . $this->table_name . "/delete/" . $mongo_id;
        return $this->makeRequest($request_url, "DELETE", false, null, [
            'accept' => 'application/json'
        ]);
    }

    /**
     * Deletes a database
     *
     * @param string|null $db_name
     * @return array
     */
    public function deleteDatabase(string $db_name = null): array
    {
        if (!$db_name) {
            return [
                "status" => false,
                "error" => "You did not provide a database name"
            ];
        }

        $request_url = $this->api_url . "/db/" . $db_name . "/delete";
        return $this->makeRequest($request_url, "DELETE", false, null, [
            'accept' => 'application/json'
        ]);
    }

    /**
     * Deletes Tables inside a database
     *
     * @param [type] $db_name
     * @param [type] $table_name
     * @return void
     */
    public function deleteTablesInDatabase($db_name = null, $table_name = null): array
    {
        if (!$table_name && !$db_name) {
            return [
                "status" => false,
                "error" => "You did not provide a valid database + table / collection name."
            ];
        }

        $request_url = $this->api_url . "/db/" . $db_name . "/" . $table_name . "/delete";
        return $this->makeRequest($request_url, "DELETE", false, null, [
            'accept' => 'application/json'
        ]);
    }
}
