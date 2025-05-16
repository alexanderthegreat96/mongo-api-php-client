# MongoDB API Client (PHP)

A PHP client library for interacting with a MongoDB RESTful API, providing a fluent interface for building queries and performing CRUD operations with robust response handling.

## Overview

The `MongoApiClient` class enables seamless interaction with a MongoDB API server, supporting operations like selecting, inserting, updating, and deleting documents. Key features include:

- **Fluent Query Building**: Chain methods like `where`, `orWhere`, `sortBy`, `groupBy` for complex queries.
- **Query Aliases**: Use `select`, `all`, `get` for `find()`, and `firstOrNone`, `one` for `first()`.
- **Auto-Conversion Control**: Toggle type conversion for query values using `autoConvert` in `where`/`orWhere`.
- **Grouped Data Handling**: Process grouped query results with `MongoApiResponseData`, including inner pagination and records.
- **Pagination Support**: Handle pagination metadata via `MongoApiResponsePagination`.
- **Retry Mechanism**: Automatically retry failed requests with exponential backoff.
- **Response Wrapping**: Normalize API responses into a consistent `MongoApiResponse` envelope.

## Installation

Install the package using Composer:

```bash
composer require alexanderthegreat96/mongo-api-client
```

Ensure PHP 7.4+ and the `guzzlehttp/guzzle` library are installed (included as a dependency).

## Usage

### Initializing the Client

Create a `MongoApiClient` instance with your API server details:

```php
<?php
use Alexanderthegreat96\MongoApiClient\MongoApiClient;

$client = new MongoApiClient(
    serverUrl: 'api.example.com',
    serverPort: 80,
    apiKey: 'your-api-key',
    scheme: 'https',
    autoConvertVals: true,
    timeoutSeconds: 10.0
);
```

### Select Queries

The library provides a powerful fluent interface for select queries, with multiple aliases for convenience. Below are examples highlighting `groupBy`, `autoConvert`, aliases, and grouped result handling.

#### Basic Select Query with Aliases

Fetch documents using `find()` or its aliases (`select`, `all`, `get`):

```php
$response = $client
    ->fromDb('my_database')
    ->fromTable('users')
    ->where('age', '>=', 18, autoConvert: true)
    ->sortBy('name', MongoApiClient::ASC)
    ->page(1)
    ->perPage(20)
    ->select(); // or all(), get()

if ($response->getStatus()) {
    $data = $response->getData();
    foreach ($data as $doc) {
        print_r($doc->getData());
    }
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

The `autoConvert: true` ensures the `age` value is tagged for automatic type conversion (e.g., `18/a` in the query string).

#### Fetching a Single Document with Aliases

Use `first()` or its aliases (`firstOrNone`, `one`) to retrieve the first matching document:

```php
$response = $client
    ->fromDb('my_database')
    ->fromTable('users')
    ->where('name', '=', 'John Doe', autoConvert: false)
    ->one(); // or firstOrNone()

if ($response->getStatus()) {
    $data = $response->getData();
    print_r($data->getData() ?: 'No document found');
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

#### Using `orWhere` with `autoConvert`

Combine `where` and `orWhere` with type conversion control:

```php
$response = $client
    ->fromDb('my_database')
    ->fromTable('users')
    ->where('age', '>=', 18, autoConvert: true)
    ->orWhere('status', '=', 'active', autoConvert: false)
    ->perPage(10)
    ->get(); // Alias for find()

if ($response->getStatus()) {
    $data = $response->getData();
    echo 'Found ' . count($data) . ' users:' . PHP_EOL;
    foreach ($data as $doc) {
        print_r($doc->getData());
    }
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

Here, `age` is tagged for conversion (`18/a`), while `status` is not (`active/n`), preserving the string value.

#### Grouped Queries with `groupBy`

Group results by a field (e.g., `city`) and handle inner pagination and records:

```php
$response = $client
    ->fromDb('my_database')
    ->fromTable('users')
    ->where('age', '>=', 18, autoConvert: true)
    ->groupBy('city')
    ->innerPage(1)
    ->innerPerPage(5)
    ->all(); // Alias for find()

if ($response->getStatus()) {
    $data = $response->getData();
    if ($data->hasGrouped()) {
        foreach ($data as $group) {
            $innerPagination = $group->getInnerPagination();
            $records = $group->getRecords();
            $totalRecords = $group->getTotalRecords();
            echo 'Group: ' . ($group->getData()['city'] ?? 'unknown') . PHP_EOL;
            echo 'Total Records: ' . $totalRecords . PHP_EOL;
            echo 'Page ' . $innerPagination->getCurrentPage() . '/' . $innerPagination->getTotalPages() . PHP_EOL;
            foreach ($records as $record) {
                echo ' - ' . print_r($record, true) . PHP_EOL;
            }
        }
    } else {
        echo 'No grouped data found' . PHP_EOL;
    }
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

The `MongoApiResponseData` class processes grouped results, providing:

- `getInnerPagination()`: A `MongoApiResponsePagination` object for inner pagination metadata (e.g., `currentPage`, `totalPages`).
- `getRecords()`: The list of records in the group.
- `getTotalRecords()`: The total count of records in the group.

Use `innerPage` and `innerPerPage` to control pagination within groups.

#### Pagination Handling

Access pagination metadata for non-grouped or grouped queries:

```php
$response = $client
    ->fromDb('my_database')
    ->fromTable('users')
    ->page(2)
    ->perPage(15)
    ->select(); // Alias for find()

if ($response->getStatus()) {
    $pagination = $response->getPagination();
    echo 'Page ' . $pagination->getCurrentPage() . '/' . $pagination->getTotalPages() . PHP_EOL;
    echo 'Items per page: ' . $pagination->getPerPage() . PHP_EOL;
    $data = $response->getData();
    foreach ($data as $doc) {
        print_r($doc->getData());
    }
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

For grouped queries, use `getInnerPagination()` on `MongoApiResponseData` for per-group pagination, as shown in the `groupBy` example.

#### Custom Select Queries

Execute custom MongoDB queries or aggregations:

```php
// Custom query
$customQuery = ['stats.timePlayed' => ['$gte' => 10000]];
$response = $client
    ->fromDb('my_database')
    ->fromTable('users')
    ->executeCustomQuery($customQuery);

// Aggregation query
$aggregateQuery = [['$match' => ['stats.timePlayed' => ['$gte' => 10000]]]];
$response = $client
    ->fromDb('my_database')
    ->fromTable('users')
    ->executeCustomQuery($aggregateQuery, aggregate: true);

if ($response->getStatus()) {
    $data = $response->getData();
    foreach ($data as $doc) {
        print_r($doc->getData());
    }
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

### Other CRUD Operations

#### Inserting Data

```php
$payload = ['name' => 'John Doe', 'age' => 30];
$response = $client->fromDb('my_database')->fromTable('users')->insert($payload);
```

#### Updating Data

```php
$payload = ['age' => 31];
$response = $client
    ->fromDb('my_database')
    ->fromTable('users')
    ->where('name', '=', 'John Doe', autoConvert: false)
    ->update($payload);
```

#### Deleting Data

```php
$response = $client
    ->fromDb('my_database')
    ->fromTable('users')
    ->where('age', '<', 18, autoConvert: true)
    ->delete();
```

### Utility Methods

List databases or tables:

```php
$dbResponse = $client->listDatabases();
print_r($dbResponse->getDatabases());

$tableResponse = $client->listTablesInDb('my_database');
print_r($tableResponse->getTables());
```

Drop databases or collections:

```php
$response = $client->dropDatabase('my_database');
$response = $client->dropCollection('my_database', 'users');
```

## Features

- **Fluent Select Queries**: Chain `where`, `orWhere`, `groupBy`, `sortBy`, with aliases (`select`, `all`, `get`, `firstOrNone`, `one`) and `autoConvert` control.
- **Grouped Data Processing**: `MongoApiResponseData` provides `innerPagination`, `records`, and `totalRecords` for grouped results.
- **Pagination Support**: `MongoApiResponsePagination` simplifies navigation of paged and inner-paged results.
- **Retry Mechanism**: Handles transient network failures with exponential backoff via Guzzle.
- **Type Safety**: Uses strict typing and PHP type declarations for better IDE support.
- **Flexible Querying**: Supports operators (`=`, `!=`, `<`, `>`, `like`, etc.) and custom MongoDB queries.

## Error Handling

Responses are wrapped in `MongoApiResponse`, providing:

- `getStatus()`: Success or failure.
- `getError()`: Error message if failed.
- `getCode()`: Status code.
- `getData()`: Documents or grouped data.

```php
$response = $client->fromDb('my_database')->fromTable('users')->select();
if (!$response->getStatus()) {
    echo 'Request failed with code ' . $response->getCode() . ': ' . $response->getError() . PHP_EOL;
}
```

## Contributing

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/YourFeature`).
3. Commit changes (`git commit -m 'Add YourFeature'`).
4. Push to the branch (`git push origin feature/YourFeature`).
5. Open a pull request.

## License

This project is licensed under the MIT License. See the `LICENSE` file for details.