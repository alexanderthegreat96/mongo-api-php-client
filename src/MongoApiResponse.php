<?php

declare(strict_types=1);

namespace Alexanderthegreat96\MongoApiClient;

class MongoApiResponse
{
    private bool    $status;
    private int     $code;
    private ?string $database;
    private ?string $table;
    private int     $count;
    private array   $pagination;
    private array   $query;
    private         $data;
    private ?string $error;
    private array   $databases;
    private array   $tables;
    private ?string $message;
    private array   $_raw;

    public function __construct(array $payload)
    {
        $this->_raw       = $payload;
        $this->status     = $payload['status']     ?? false;
        $this->code       = $payload['code']       ?? 500;
        $this->database   = $payload['database']   ?? null;
        $this->table      = $payload['table']      ?? null;
        $this->count      = $payload['count']      ?? 0;
        $this->pagination = $payload['pagination'] ?? [];
        $this->query      = $payload['query']      ?? [];
        $this->data       = $payload['data']       ?? null;
        $this->error      = $payload['error']      ?? null;
        $this->databases  = $payload['databases']  ?? [];
        $this->tables     = $payload['tables']     ?? [];
        $this->message    = $payload['message']    ?? null;
    }

    public function getStatus(): bool         { return $this->status; }
    public function getCode(): int            { return $this->code; }
    public function getDatabase(): ?string    { return $this->database; }
    public function getTable(): ?string       { return $this->table; }
    public function getCount(): int           { return $this->count; }
    public function getPagination(): array    { return $this->pagination; }
    public function getQuery(): array         { return $this->query; }
    public function getData()                 { return $this->data; }
    public function getError(): ?string       { return $this->error; }
    public function getDatabases(): array     { return $this->databases; }
    public function getTables(): array        { return $this->tables; }
    public function getMessage(): ?string     { return $this->message; }
    public function getRaw(): array           { return $this->_raw; }

    public function __toString(): string
    {
        return sprintf(
            '<MongoApiResponse status=%s code=%d db=%s table=%s count=%d>',
            $this->status ? 'true' : 'false',
            $this->code,
            $this->database ?? 'null',
            $this->table ?? 'null',
            $this->count
        );
    }
}
