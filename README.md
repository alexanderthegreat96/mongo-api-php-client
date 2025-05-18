# MongoDB API Client (PHP)

A PHP client library for interacting with a MongoDB RESTful API, providing a fluent interface for building queries and performing CRUD operations with robust response handling.

## Overview

The `MongoApiClient` class enables seamless interaction with a MongoDB API server, supporting operations like selecting, inserting, updating, and deleting documents. Key features include:

- **Fluent Query Building**: Chain methods like `where`, `orWhere`, `sortBy`, `groupBy` for complex queries.
- **Query Aliases**: Use `all` (preferred), `select`, `get` for `find()`, and `first` (preferred), `firstOrNone`, `one` for `first()`.
- **Auto-Conversion Control**: Toggle type conversion for query values using `autoConvert` in `where`/`orWhere`.
- **Grouped Data Handling**: Process grouped query results with `MongoApiResponseData`, including inner pagination and records.
- **Pagination Support**: Handle pagination metadata via `MongoApiResponsePagination`.
- **Retry Mechanism**: Automatically retry failed requests with configurable exponential backoff.
- **Response Wrapping**: Normalize API responses into a consistent `MongoApiResponse` envelope.
- **Iterator and Countable Data**: `MongoApiResponseData` supports iteration and counting for flexible data handling.

## Installation

Install the package using Composer:

```bash
composer require alexanderthegreat96/mongo-api-client
```

Ensure PHP 8.1+ and the `guzzlehttp/guzzle` library are installed (included as a dependency).

## Configuration

Configure the client using constructor parameters or environment variables for sensitive data like API keys. Supported environment variables:

- `MONGO_API_URL`: API server URL (e.g., `api.example.com`).
- `MONGO_API_PORT`: Server port (default: `80`).
- `MONGO_API_KEY`: API key for authentication Cage for MongoDB API Client.
- `MONGO_API_SCHEME`: Protocol (`http` or `https`, default: `https`).
- `MONGO_API_TIMEOUT`: Request timeout in seconds (default: `5.0`).

Example using environment variables:

```php
<?php
use Alexanderthegreat96\MongoApiClient\MongoApiClient;

$client = new MongoApiClient(
    serverUrl: env('MONGO_API_URL', 'api.example.com'),
    serverPort: env('MONGO_API_PORT', 80),
    apiKey: env('MONGO_API_KEY', 'your-api-key'),
    scheme: env('MONGO_API_SCHEME', 'https'),
    autoConvertVals: true,
    timeoutSeconds: env('MONGO_API_TIMEOUT', 5.0)
);
```

## Usage

### Initializing the Client

Create a `MongoApiClient` instance with your API server details:

```php
<?php
use Alexanderthegreat96\MongoApiClient\MongoApiClient;

$client = new MongoApiClient(
   'api.example.com',
    80,
    'your-api-key',
    'https',
    true,
    5.0
);
```

### Authentication

The client uses API key authentication by default. Pass the API key in the constructor or via the `MONGO_API_KEY` environment variable.

### Select Queries

The library provides a powerful fluent interface for select queries, with `all()` as the preferred method for fetching multiple documents and `first()` for a single document. Use `getResult()` to access individual document data for single or multiple results, `getRecords()` for grouped query results, and `getCount()` for counting documents.

#### Fetching Multiple Documents

Use `all()` (preferred over `select()` or `get()`) to fetch multiple documents. Iterate through the results and call `getResult()` on each:

```php
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->where('age', '>=', 18, autoConvert: true)
    ->sortBy('name', MongoApiClient::ASC)
    ->page(1)
    ->perPage(20)
    ->all();

if ($response->isOk()) {
    $data = $response->getData();
    echo 'Found ' . count($data) . ' users:' . PHP_EOL;
    foreach ($data as $doc) {
        print_r($doc->getResult());
    }
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

The `autoConvert: true` ensures the `age` value is tagged for automatic type conversion (e.g., `18/a` in the query string). The `MongoApiResponseData` object is countable, allowing `count($data)`.

#### Fetching a Single Document

Use `first()` (preferred over `firstOrNone()` or `one()`) to retrieve the first matching document. Access the document with `getResult()`:

```php
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->where('name', '=', 'John Doe', autoConvert: false)
    ->first();

if ($response->isOk()) {
    $data = $response->getData();
    print_r($data->getResult() ?: 'No document found');
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

#### Fetching a Document by ID

Use `findById()` to retrieve a document by its `_id` and access it with `getResult()`:

```php
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->findById('507f1f77bcf86cd799439011');

if ($response->isOk()) {
    $data = $response->getData();
    print_r($data->getResult() ?: 'No document found');
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

#### Using `orWhere` with `autoConvert`

Combine `where` and `orWhere` for complex queries, using `all()` to fetch multiple documents:

```php
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->where('age', '>=', 18, autoConvert: true)
    ->orWhere('status', '=', 'active', autoConvert: false)
    ->perPage(10)
    ->all();

if ($response->isOk()) {
    $data = $response->getData();
    echo 'Found ' . count($data) . ' users:' . PHP_EOL;
    foreach ($data as $doc) {
        print_r($doc->getResult());
    }
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

Here, `age` is tagged for conversion (`18/a`), while `status` is not (`active/n`), preserving the string value.

#### Grouped Queries with `groupBy`

Group results by a field (e.g., `city`) and use `getRecords()` to access records within each group:

```php
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->where('age', '>=', 18, autoConvert: true)
    ->groupBy('city')
    ->innerPage(1)
    ->innerPerPage(5)
    ->all();

if ($response->isOk()) {
    $data = $response->getData();
    if ($data->hasGrouped()) {
        foreach ($data as $group) {
            $innerPagination = $group->getInnerPagination();
            $records = $group->getRecords();
            $totalRecords = $group->getTotalRecords();
            $recordId = $group->getRecordId();
            echo 'Group ID: ' . ($recordId ?? 'unknown') . PHP_EOL;
            echo 'Group: ' . ($group->getData()['city'] ?? 'unknown') . PHP_EOL;
            echo 'Total Records: ' . ($totalRecords[0] ?? 0) . PHP_EOL;
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

The `MongoApiResponseData` class provides:
- `getRecordId()`: The `_id` of the group (e.g., the `city` value).
- `getInnerPagination()`: A `MongoApiResponsePagination` object for inner pagination metadata.
- `getRecords()`: The list of records in the group.
- `getTotalRecords()`: The total count of records in the group.

Use `innerPage` and `innerPerPage` (or `innerLimit`) to control pagination within groups. The class implements `IteratorAggregate` for foreach loops and `Countable` for counting records.

#### Counting Documents

Use `count()` to get the number of matching documents. Check `isOk()` and call `getCount()`:

```php
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->where('age', '>=', 18, autoConvert: true)
    ->count();

if ($response->isOk()) {
    echo 'Total users: ' . $response->getCount() . PHP_EOL;
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

#### Pagination Handling

Access pagination metadata for non-grouped or grouped queries:

```php
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->page(2)
    ->perPage(15)
    ->all();

if ($response->isOk()) {
    $pagination = $response->getPagination();
    echo 'Page ' . $pagination->getCurrentPage() . '/' . $pagination->getTotalPages() . PHP_EOL;
    echo 'Next Page: ' . $pagination->getNextPage() . PHP_EOL;
    echo 'Previous Page: ' . $pagination->getPrevPage() . PHP_EOL;
    echo 'Items per page: ' . $pagination->getPerPage() . PHP_EOL;
    $data = $response->getData();
    foreach ($data as $doc) {
        print_r($doc->getResult());
    }
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

For grouped queries, use `getInnerPagination()` on `MongoApiResponseData` for per-group pagination, as shown in the `groupBy` example.

#### Custom Select Queries

Execute custom MongoDB queries or aggregations, using `all()`-style result handling:

```php
// Custom query
$customQuery = ['stats.timePlayed' => ['$gte' => 10000]];
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->executeCustomQuery($customQuery);

if ($response->isOk()) {
    $data = $response->getData();
    foreach ($data as $doc) {
        print_r($doc->getResult());
    }
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}

// Aggregation query
$aggregateQuery = [
    ['$match' => ['stats.timePlayed' => ['$gte' => 10000]]],
    ['$group' => [
        '_id' => '$city',
        'totalTime' => ['$sum' => '$stats.timePlayed'],
        'avgTime' => ['$avg' => '$stats.timePlayed']
    ]],
    ['$sort' => ['totalTime' => -1]]
];
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->executeCustomQuery($aggregateQuery, aggregate: true);

if ($response->isOk()) {
    $data = $response->getData();
    foreach ($data as $doc) {
        print_r($doc->getResult());
    }
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

Use `aggregate: true` to execute as a pipeline query.

### Other CRUD Operations

#### Inserting Data

```php
$payload = ['name' => 'John Doe', 'age' => 30];
$response = $client->fromDb('my_database')->fromCollection('users')->insert($payload);

if ($response->isOk()) {
    echo 'Inserted document. Message: ' . $response->getMessage() . PHP_EOL;
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

#### Conditional Inserting

Use `insertIf()` to insert a document only if it matches the query conditions:

```php
$payload = ['name' => 'John Doe', 'age' => 30];
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->where('name', '=', 'John Doe', autoConvert: false)
    ->insertIf($payload);

if ($response->isOk()) {
    echo 'Inserted document. Message: ' . $response->getMessage() . PHP_EOL;
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

#### Updating Data

```php
$payload = ['age' => 31];
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->where('name', '=', 'John Doe', autoConvert: false)
    ->update($payload);

if ($response->isOk()) {
    echo 'Updated documents. Message: ' . $response->getMessage() . PHP_EOL;
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

#### Updating by ID

Use `updateById()` to update a specific document by its `_id`:

```php
$payload = ['age' => 31];
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->updateById('507f1f77bcf86cd799439011', $payload);

if ($response->isOk()) {
    echo 'Updated document. Message: ' . $response->getMessage() . PHP_EOL;
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

#### Deleting Data

```php
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->where('age', '<', 18, autoConvert: true)
    ->delete();

if ($response->isOk()) {
    echo 'Deleted documents. Message: ' . $response->getMessage() . PHP_EOL;
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

#### Deleting by ID

Use `deleteById()` to delete a specific document by its `_id`:

```php
$response = $client
    ->fromDb('my_database')
    ->fromCollection('users')
    ->deleteById('507f1f77bcf86cd799439011');

if ($response->isOk()) {
    echo 'Deleted document. Message: ' . $response->getMessage() . PHP_EOL;
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

### Utility Methods

List databases or collections:

```php
$dbResponse = $client->listDatabases();
if ($dbResponse->isOk()) {
    print_r($dbResponse->getDatabases());
} else {
    echo 'Error: ' . $dbResponse->getError() . PHP_EOL;
}

$collectionResponse = $client->listTablesInDb('my_database');
if ($collectionResponse->isOk()) {
    print_r($collectionResponse->getTables());
} else {
    echo 'Error: ' . $collectionResponse->getError() . PHP_EOL;
}
```

Drop databases or collections:

```php
$response = $client->dropDatabase('my_database');
if ($response->isOk()) {
    echo 'Dropped database. Message: ' . $response->getMessage() . PHP_EOL;
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}

$response = $client->dropCollection('my_database', 'users');
if ($response->isOk()) {
    echo 'Dropped collection. Message: ' . $response->getMessage() . PHP_EOL;
} else {
    echo 'Error: ' . $response->getError() . PHP_EOL;
}
```

### Features

- **Fluent Select Queries**: Chain `where`, `orWhere`, `groupBy`, `sortBy`, with aliases (`all` preferred, `select`, `get`; `first` preferred, `firstOrNone`, `one`) and `autoConvert` control.
- **Grouped Data Processing**: `MongoApiResponseData` provides `recordId`, `innerPagination`, `records`, and `totalRecords` for grouped results, with iterator and countable support.
- **Pagination Support**: `MongoApiResponsePagination` includes `currentPage`, `totalPages`, `nextPage`, `prevPage`, `lastPage`, and `perPage`.
- **Retry Mechanism**: Handles transient network failures with exponential backoff via Guzzle.
- **Type Safety**: Uses strict typing and PHP type declarations for better IDE support.
- **Flexible Querying**: Supports operators (`=`, `!=`, `<`, `>`, `like`, etc.), custom MongoDB queries, and pipeline aggregations.
- **ID-Based Operations**: Supports `findById`, `updateById`, and `deleteById` for precise document manipulation.
- **Result Handling**: Use `getResult()` for single/multiple document data and `getRecords()` for grouped query results.

## Error Handling

Responses are wrapped in `MongoApiResponse`, providing:
- `isOk()`/`isNotOk()`: Check success or failure.
- `getError()`: Error message if failed.
- `getCode()`: HTTP status code (e.g., `400`, `401`, `429`, `500`).
- `getData()`: Documents or grouped data as `MongoApiResponseData`.
- `getMessage()`: Additional response message.
- `getRaw()`: Raw API response payload.

Common error codes:
- `400`: Invalid query or payload.
- `401`: Authentication failure (invalid API key).
- `429`: Rate limit exceeded.
- `500`: Server error.

Example handling errors:

```php
$response = $client->fromDb('my_database')->fromCollection('users')->all();
if ($response->isNotOk()) {
    echo 'Request failed with code ' . $response->getCode() . ': ' . $response->getError() . PHP_EOL;
    if ($response->getCode() === 401) {
        echo 'Please check your API key.' . PHP_EOL;
    }
}
```

## Changelog

### Version 1.2.1 (2025-05-18)
- Updated documentation to emphasize `all()` for multiple documents and `first()` for single documents.
- Clarified result handling: use `getResult()` for single/multiple results, `getRecords()` for grouped data, and `getCount()` for counting.
- Standardized examples to use `isOk()` and `fromCollection()` consistently.
- Removed OAuth references as they are not supported in the current codebase.

### Version 1.2.0 (2025-05-18)
- Added `isOk()`, `isNotOk()`, `getRaw()`, and `getMessage()` to `MongoApiResponse`.
- Enhanced `MongoApiResponseData` with `IteratorAggregate`, `Countable`, `getRecordId()`, `getResults()`, and `getResult()`.
- Added `findById()`, `insertIf()`, `updateById()`, `deleteById()`, and `innerLimit()` to `MongoApiClient`.
- Added `getNextPage()`, `getPrevPage()`, `getLastPage()`, and `getPayload()` to `MongoApiResponsePagination`.
- Improved type safety and PHPDoc annotations.
- Updated terminology to use “collection” instead of “table” for MongoDB consistency.

### Version 1.1.0 (2025-05-18)
- Added support for environment variable configuration.
- Enhanced `executeCustomQuery` with complex aggregation examples.
- Improved error handling with specific error code documentation.

### Version 1.0.0
- Initial release with fluent query building, CRUD operations, grouped data handling, and pagination support.

## License

This project is licensed under the MIT License. See the `LICENSE` file for details.