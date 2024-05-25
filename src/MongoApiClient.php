<?php

namespace Alexanderthegreat96\MoongoApiClient;

use GuzzleHttp\Client;

class MongoApiClient
{
    private $server_url;
    private $server_port;
    private $api_url;
    private $db_name;
    private $table_name;
    private $per_page;
    private $page;
    private $query_params;
    private $where_query;
    private $or_where_query;
    private $sort_by_list;
    private $operator_map;
    private $sort_order;
    private $guzzle;

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
    }

    private function assembleQuery()
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
    }

    private function makeRequest(string $url, string $method, bool $queryParams = false, array $data = null, array $headers = [])
    {
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

    public function listDatabases(): array
    {
        $request_url = $this->api_url . "/db/databases";
        return $this->makeRequest($request_url, "GET", false, null, [
            'accept' => 'application/json'
        ]);
    }

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
    public function fromDb($db_name = null)
    {
        if ($db_name) {
            $this->db_name = $db_name;
        }
        return $this;
    }

    public function intoDb($db_name = null)
    {
        if ($db_name) {
            $this->db_name = $db_name;
        }
        return $this;
    }

    public function fromTable($table_name = null)
    {
        if ($table_name) {
            $this->table_name = $table_name;
        }
        return $this;
    }

    public function intoTable($table_name = null)
    {
        if ($table_name) {
            $this->table_name = $table_name;
        }
        return $this;
    }

    public function where($col_name = null, $operator = null, $col_val = null)
    {
        if (array_key_exists($operator, $this->operator_map)) {
            $this->where_query[] = $col_name . "," . $this->operator_map[$operator] . "," . strval($col_val);
        }
        return $this;
    }

    public function orWhere($col_name = null, $operator = null, $col_val = null)
    {
        if (array_key_exists($operator, $this->operator_map)) {
            $this->or_where_query[] = $col_name . "," . $this->operator_map[$operator] . "," . strval($col_val);
        }
        return $this;
    }

    public function perPage($per_page = 0)
    {
        if ($per_page > 0) {
            $this->per_page = $per_page;
        }
        return $this;
    }

    public function page($page = 0)
    {
        if ($page > 0) {
            $this->page = $page;
        }
        return $this;
    }

    public function sortBy($col_name = null, $direction = null)
    {
        if (in_array($direction, $this->sort_order)) {
            $this->sort_by_list[] = $col_name . ":" . $direction;
        }
        return $this;
    }

    public function select(): array
    {
        $this->assembleQuery();
        $request_url = $this->api_url . "/db/" . $this->db_name . "/" . $this->table_name . "/select";

        return $this->makeRequest($request_url, "GET", true, null, [
            "accept: application/json"
        ]);
    }

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

    public function delete()
    {
        $this->assembleQuery();

        $request_url = $this->api_url . "/db/" . $this->db_name . "/" . $this->table_name . "/delete-where";
        return $this->makeRequest($request_url, "DELETE", true, null, [
            'accept' => 'application/json'
        ]);
    }

    public function deleteById(string $mongo_id = null): array
    {
        if (!$mongo_id) {
            return array(
                "status" => false,
                "error" => "You failed to provide a mongo_id to send to the server."
            );
        }

        $request_url = $this->api_url . "/db/" . $this->db_name . "/" . $this->table_name . "/delete/" . $mongo_id;
        return $this->makeRequest($request_url, "DELETE", false, null, [
            'accept' => 'application/json'
        ]);
    }

    public function deleteDatabase(string $db_name = null): array
    {
        if (!$db_name) {
            return array(
                "status" => false,
                "error" => "You did not provide a database name"
            );
        }

        $request_url = $this->api_url . "/db/" . $db_name . "/delete";
        return $this->makeRequest($request_url, "DELETE", false, null, [
            'accept' => 'application/json'
        ]);
    }

    public function deleteTablesInDatabase($db_name = null, $table_name = null)
    {
        if (!$table_name && !$db_name) {
            return array(
                "status" => false,
                "error" => "You did not provide a valid database + table / collection name."
            );
        }

        $request_url = $this->api_url . "/db/" . $db_name . "/" . $table_name . "/delete";
        return $this->makeRequest($request_url, "DELETE", false, null, [
            'accept' => 'application/json'
        ]);
    }
}
