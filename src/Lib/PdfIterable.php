<?php

namespace Lle\PdfGeneratorBundle\Lib;

use \PDFMerger as Base;

class PdfIterable implements \Iterator, \Countable
{
    private $data;

    private $position;

    public function __construct(iterable $data)
    {
        $this->data = $data;
        $this->position = 0;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function current(): mixed
    {
        return $this->data[$this->position];
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function valid(): bool
    {
        return isset($this->data[$this->position]);
    }

    public function count(): int
    {
        return count($this->data);
    }
}