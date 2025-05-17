<?php

declare(strict_types=1);

namespace Alexanderthegreat96\MongoApiClient;

use ArrayIterator;
use IteratorAggregate;
use Countable;

class MongoApiResponseData implements IteratorAggregate, Countable
{
    /** @var array<string, mixed>|array<int, array<string, mixed>> */
    private array $payload;

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function getIterator(): ArrayIterator
    {
        // if list of docs, wrap each item; else yield $this
        if (array_is_list($this->payload)) {
            return new ArrayIterator(array_map(
                fn(array $item) => new self($item),
                $this->payload
            ));
        }

        return new ArrayIterator([$this]);
    }

    public function count(): int
    {
        return array_is_list($this->payload)
            ? count($this->payload)
            : 1;
    }

    private function isGroupedDoc(array $doc): bool
    {
        return isset($doc['inner_pagination'], $doc['records'], $doc['total_records']);
    }

    public function hasGrouped(): bool
    {
        if (! array_is_list($this->payload)) {
            return $this->isGroupedDoc($this->payload);
        }

        foreach ($this->payload as $doc) {
            if (is_array($doc) && $this->isGroupedDoc($doc)) {
                return true;
            }
        }
        return false;
    }

    public function getRecordId()
    {
        if (! $this->hasGrouped()) {
            return null;
        }

        if (! array_is_list($this->payload)) {
            return $this->payload['_id'] ?? null;
        }

        return array_map(
            fn($doc) => isset($doc['_id']) ?? null, $this->payload
        );
    }
    /**
     * @return MongoApiResponsePagination|MongoApiResponsePagination[]|null
     */
    public function getInnerPagination()
    {
        if (! $this->hasGrouped()) {
            return null;
        }

        if (! array_is_list($this->payload)) {
            $inner = $this->payload['inner_pagination'] ?? null;
            return $inner !== null
                ? new MongoApiResponsePagination($inner)
                : null;
        }

        return array_map(
            fn($doc) => isset($doc['inner_pagination'])
                ? new MongoApiResponsePagination($doc['inner_pagination'])
                : null,
            $this->payload
        );
    }

    /**
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    public function getRecords(): ?array
    {
        if (! $this->hasGrouped()) {
            return null;
        }

        if (! array_is_list($this->payload)) {
            return $this->payload['records'] ?? [];
        }

        return array_map(
            fn($doc) => $doc['records'] ?? null,
            $this->payload
        );
    }

    /**
     * @return int|int[]|null
     */
    public function getTotalRecords()
    {
        if (! $this->hasGrouped()) {
            return null;
        }

        if (! array_is_list($this->payload)) {
            $val = $this->payload['total_records'] ?? null;
            return is_numeric($val) ? (int)$val : null;
        }

        return array_map(
            fn($doc) => (isset($doc['total_records']) && is_numeric($doc['total_records']))
                ? (int)$doc['total_records']
                : null,
            $this->payload
        );
    }

    /**
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        return $this->payload;
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
