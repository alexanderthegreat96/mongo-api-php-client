<?php

declare(strict_types=1);

namespace Alexanderthegreat96\MongoApiClient;

use ArrayIterator;
use IteratorAggregate;
use Countable;

class MongoApiResponseData implements IteratorAggregate, Countable
{
    /** @var array|array[] */
    private $_payload;

    /**
     * @param array|array[] $payload
     */
    public function __construct(?array $payload)
    {
        $this->_payload = $payload;
    }

    public function getIterator(): ArrayIterator
    {
        if (is_array($this->_payload) && $this->isList($this->_payload)) {
            return new ArrayIterator(array_map(
                fn(array $item) => new self($item),
                $this->_payload
            ));
        }
        return new ArrayIterator([ $this ]);
    }

    public function count(): int
    {
        if (is_array($this->_payload) && $this->isList($this->_payload)) {
            return count($this->_payload);
        }
        return 1;
    }

    private function isList(array $a): bool
    {
        // numeric keys only → list
        return array_keys($a) === range(0, count($a) - 1);
    }

    public function hasGrouped(): bool
    {
        $docs = $this->isList($this->_payload) ? $this->_payload : [ $this->_payload ];
        foreach ($docs as $doc) {
            if (isset($doc['inner_pagination'], $doc['records'], $doc['total_records'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return MongoApiResponsePagination|MongoApiResponsePagination[]|null
     */
    public function getInnerPagination()
    {
        if (! $this->hasGrouped()) {
            return null;
        }

        if (! $this->isList($this->_payload)) {
            return new MongoApiResponsePagination($this->_payload['inner_pagination'] ?? []);
        }

        $wrapped = [];
        foreach ($this->_payload as $doc) {
            if (isset($doc['inner_pagination'])) {
                $wrapped[] = new MongoApiResponsePagination($doc['inner_pagination']);
            } else {
                $wrapped[] = null;
            }
        }
        return $wrapped;
    }

    /**
     * @return array|array[]|null
     */
    public function getRecords(): ?array
    {
        if (! $this->hasGrouped()) {
            return null;
        }

        if (! $this->isList($this->_payload)) {
            return $this->_payload['records'] ?? [];
        }

        return array_map(
            fn($doc) => $doc['records'] ?? null,
            $this->_payload
        );
    }

    /**
     * @return int|int[]|null
     */
    public function getTotalRecords(): ?int
    {
        if (! $this->hasGrouped()) {
            return null;
        }

        if (! $this->isList($this->_payload)) {
            return isset($this->_payload['total_records'])
                ? (int)$this->_payload['total_records']
                : null;
        }

        return null;
    }

    /**
     * @return array|array[]
     */
    public function getData(): ?array
    {
        return $this->_payload;
    }

    public function __toString(): string
    {
        $items = $this->count();
        $grp   = $this->hasGrouped() ? 'true' : 'false';
        return sprintf(
            '<MongoApiResponseData items=%d grouped=%s>',
            $items,
            $grp
        );
    }
}