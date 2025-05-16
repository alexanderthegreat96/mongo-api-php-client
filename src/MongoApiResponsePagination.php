<?php

declare(strict_types=1);

namespace Alexanderthegreat96\MongoApiClient;

class MongoApiResponsePagination
{
    private array $_payload;

    public function __construct(array $payload)
    {
        $this->_payload = $payload;
    }

    public function getTotalPages(): int
    {
        return isset($this->_payload['total_pages'])
            ? (int)$this->_payload['total_pages']
            : 1;
    }

    public function getCurrentPage(): int
    {
        return isset($this->_payload['current_page'])
            ? (int)$this->_payload['current_page']
            : 1;
    }

    public function getNextPage(): int
    {
        return isset($this->_payload['next_page'])
            ? (int)$this->_payload['next_page']
            : 1;
    }

    public function getPrevPage(): int
    {
        return isset($this->_payload['prev_page'])
            ? (int)$this->_payload['prev_page']
            : 1;
    }

    public function getLastPage(): int
    {
        return isset($this->_payload['last_page'])
            ? (int)$this->_payload['last_page']
            : 1;
    }

    public function getPerPage(): int
    {
        return isset($this->_payload['per_page'])
            ? (int)$this->_payload['per_page']
            : 1;
    }

    public function getPayload(): array
    {
        return $this->_payload;
    }

    public function __toString(): string
    {
        $current = $this->getCurrentPage();
        $total   = $this->getTotalPages();
        $perPage = $this->getPerPage();
        return sprintf(
            '<MongoApiResponsePagination page=%d/%d per_page=%d>',
            $current,
            $total,
            $perPage
        );
    }
}