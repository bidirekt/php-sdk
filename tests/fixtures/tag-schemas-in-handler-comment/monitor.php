<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Monitor
{
    #[BidirektField(type: 'Health')]
    public $latest;

    #[BidirektField(type: 'map', values: ['type' => 'array', 'items' => 'HealthCheck'])]
    public $history;
}

/** @bidirekt schema */
final class Probe
{
    /** @bidirekt field type=HealthCheck */
    public object $check;
}
