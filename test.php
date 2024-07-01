<?php

use Alexanderthegreat96\MongoApiClient\MongoApiClient;

require "vendor/autoload.php";

$mongo = new MongoApiClient("localhost", 9875, 'http');

// $select = $mongo
//     ->fromDb("my-test-database")
//     ->fromTable("my-test-table")
//     ->orWhere("username", "=", "popeye1212")
//     ->orWhere("age", ">", 34)
//     ->sortBy("created_at", "asc")
//     ->page(1)
//     ->perPage(10)
//     ->select();

// var_dump($select);

// $selectById = $mongo->fromDb("my-test-database")->fromTable("my-test-table")->selectById("665104538e80ecc6f646d6ce");

// var_dump($selectById);

// $updateWhere = $mongo->fromDb("my-test-database")->fromTable("my-test-table")->where("username", "=", "popeye1212")->update([
//     "age" => 56
// ]);

// var_dump($updateWhere);

// $updateById = $mongo->fromDb("my-test-database")->fromTable("my-test-table")->updateById("6651042f8e80ecc6f646d6ca", [
//     "age" => 21
// ]);

// var_dump($updateById);

// $insert = $mongo->intoDb("my-test-database")->intoTable("my-test-table")->insert([
//     [
//         "username" => "randomUser123",
//         "password" => "randomPassword123",
//         "age" => 25,
//         "scores" => [
//             "goals" => 8,
//             "k/d" => 1.5,
//             "isPlayer" => true
//         ]
//     ],
//     [
//         "username" => "user123",
//         "password" => "pass123",
//         "age" => 30,
//         "scores" => [
//             "goals" => 15,
//             "k/d" => 2.3,
//             "isPlayer" => true
//         ]
//     ],
//     [
//         "username" => "player456",
//         "password" => "football789",
//         "age" => 22,
//         "scores" => [
//             "goals" => 20,
//             "k/d" => 1.8,
//             "isPlayer" => true
//         ]
//     ],
//     [
//         "username" => "gamer_abc",
//         "password" => "gaming456",
//         "age" => 28,
//         "scores" => [
//             "goals" => 12,
//             "k/d" => 1.2,
//             "isPlayer" => true
//         ]
//     ],
//     [
//         "username" => "johnDoe",
//         "password" => "john123",
//         "age" => 35,
//         "scores" => [
//             "goals" => 5,
//             "k/d" => 0.9,
//             "isPlayer" => false
//         ]
//     ],
//     [
//         "username" => "testUser",
//         "password" => "test123",
//         "age" => 26,
//         "scores" => [
//             "goals" => 18,
//             "k/d" => 1.6,
//             "isPlayer" => true
//         ]
//     ]
// ]);

// var_dump($insert);

// $insertIf = $mongo->intoDb("my-test-database")->intoTable("my-test-table")->where("username", "=", "rnaskdjnkj1n23123")->insertIf([
//     "username" => "testUser",
//     "password" => "test123",
//     "age" => 26,
//     "scores" => [
//         "goals" => 18,
//         "k/d" => 1.6,
//         "isPlayer" => true
//     ]
// ]);

// var_dump($insertIf);

// $deleteWhere = $mongo->fromDb("my-test-database")->fromTable("my-test-table")->where("username", "=", "testUser")->delete();

// var_dump($deleteWhere);

// $deleteById = $mongo->fromDb("my-test-database")->fromTable("my-test-table")->deleteById("6651fa298e80ecc6f646d853");

// var_dump($deleteById);

// $count = $mongo
//     ->fromDb("isac-division2-bot")
//     ->fromTable("account-versioning")
//     ->where("username", "=", "LMAO-B")
//     ->count();

// var_dump($count);
