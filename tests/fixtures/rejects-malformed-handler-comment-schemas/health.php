<?php

/**
 * @bidirekt provides GET /health
 * @bidirekt response 200 Health
 * @bidirekt field status type=string
 *
 * @bidirekt schema Health
 * @bidirekt field uptime
 * @bidirekt field checks type=HealthCheck[]
 * @bidirekt field name=status type=string
 *
 * @bidirekt schema name=Probe
 * @bidirekt field ok type=boolean
 */
function health() {}

/**
 * @bidirekt provides GET /status
 * @bidirekt response 200 Health
 *
 * @bidirekt schema Health
 * @bidirekt field state type=string
 */
function status() {}

/**
 * @bidirekt consumes metrics_api GET /uptime
 * @bidirekt response 200 Uptime
 *
 * @bidirekt schema Uptime
 * @bidirekt field since type=Instant
 * @bidirekt field samples type=map values=Sample
 * @bidirekt field labels type=map<string>
 * @bidirekt field source type=string union={probe: Probe, agent: Agent}
 */
function uptime() {}

/** @bidirekt schema */
final class Monitor
{
    /** @bidirekt field type=Missing */
    public $latest;
}
