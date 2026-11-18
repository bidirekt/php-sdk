<?php

namespace Acme\Http;

trait ConditionallyLoadsAttributes
{
    protected function when($condition, $value)
    {
        return $condition ? $value : null;
    }
}
