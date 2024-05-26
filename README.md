# MongoApiClient

MongoApiClient is a PHP library for interacting with MongoDB through an API. This client allows you to perform CRUD operations, as well as other common database tasks.

## Installation

To install the library, use Composer:

```bash
composer require alexanderthegreat96/mongo-api-client
```


## Usage
 - The package provides a FLUENT syntax to interact with the MongoDB API instance, as if it were stored on this system

## Examples

```php
<?php

use Alexanderthegreat96\MongoApiClient\MongoApiClient;

require "vendor/autoload.php";

$mongo = new MongoApiClient("my-test-server", 9875, 'http');

// Select records with conditions, sorting, and pagination
$select = $mongo
    ->fromDb("my-test-database")
    ->fromTable("my-test-table")
    ->orWhere("username", "=", "popeye1212")
    ->orWhere("age", ">", 34)
    ->sortBy("created_at", "asc")
    ->page(1)
    ->perPage(10)
    ->select(); // find()

var_dump($select);

// Retrieve the first result

$first = $mongo
    ->fromDb("my-test-database")
    ->fromTable("my-test-table")
    ->orWhere("username", "=", "popeye1212")
    ->orWhere("age", ">", 34)
    ->sortBy("created_at", "asc")
    ->get()
    ->first(); // you have to provide get() before this

var_dump($first);

// Select a record by ID
$selectById = $mongo
->fromDb("my-test-database")
->fromTable("my-test-table")
->selectById("665104538e80ecc6f646d6ce"); // findById("your-mongo-id")

var_dump($selectById);

// return result count
// you may provide additional 
// conditions

$count = $mongo
->fromDb("my-test-database")
->fromTable("my-test-table")
->count();

var_dump($count);

// Update records with conditions
$updateWhere = $mongo->fromDb("my-test-database")->fromTable("my-test-table")->where("username", "=", "popeye1212")->update([
    "age" => 56
]);

var_dump($updateWhere);

// Update a record by ID
$updateById = $mongo->fromDb("my-test-database")->fromTable("my-test-table")->updateById("6651042f8e80ecc6f646d6ca", [
    "age" => 21
]);

var_dump($updateById);

// Insert multiple records
$insert = $mongo->intoDb("my-test-database")->intoTable("my-test-table")->insert([
    [
        "username" => "randomUser123",
        "password" => "randomPassword123",
        "age" => 25,
        "scores" => [
            "goals" => 8,
            "k/d" => 1.5,
            "isPlayer" => true
        ]
    ],
    [
        "username" => "user123",
        "password" => "pass123",
        "age" => 30,
        "scores" => [
            "goals" => 15,
            "k/d" => 2.3,
            "isPlayer" => true
        ]
    ],
    [
        "username" => "player456",
        "password" => "football789",
        "age" => 22,
        "scores" => [
            "goals" => 20,
            "k/d" => 1.8,
            "isPlayer" => true
        ]
    ],
    [
        "username" => "gamer_abc",
        "password" => "gaming456",
        "age" => 28,
        "scores" => [
            "goals" => 12,
            "k/d" => 1.2,
            "isPlayer" => true
        ]
    ],
    [
        "username" => "johnDoe",
        "password" => "john123",
        "age" => 35,
        "scores" => [
            "goals" => 5,
            "k/d" => 0.9,
            "isPlayer" => false
        ]
    ],
    [
        "username" => "testUser",
        "password" => "test123",
        "age" => 26,
        "scores" => [
            "goals" => 18,
            "k/d" => 1.6,
            "isPlayer" => true
        ]
    ]
]);

var_dump($insert);

// Insert a record if a condition is met
$insertIf = $mongo->intoDb("my-test-database")->intoTable("my-test-table")->where("username", "=", "rnaskdjnkj1n23123")->insertIf([
    "username" => "testUser",
    "password" => "test123",
    "age" => 26,
    "scores" => [
        "goals" => 18,
        "k/d" => 1.6,
        "isPlayer" => true
    ]
]);

var_dump($insertIf);

// Delete records with conditions
$deleteWhere = $mongo->fromDb("my-test-database")->fromTable("my-test-table")->where("username", "=", "testUser")->delete();

var_dump($deleteWhere);

// Delete a record by ID
$deleteById = $mongo->fromDb("my-test-database")->fromTable("my-test-table")->deleteById("6651fa298e80ecc6f646d853");

var_dump($deleteById);

// List all tables in a database
var_dump($mongo->listTablesInDb("xdf-player-data"));
```