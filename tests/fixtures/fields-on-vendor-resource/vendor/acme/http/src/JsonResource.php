<?php

namespace Acme\Http;

class JsonResource implements \JsonSerializable
{
    use ConditionallyLoadsAttributes;

    public $resource;

    public function __construct($resource)
    {
        $this->resource = $resource;
    }

    public function toArray($request)
    {
        return (array) $this->resource;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray(null);
    }
}
