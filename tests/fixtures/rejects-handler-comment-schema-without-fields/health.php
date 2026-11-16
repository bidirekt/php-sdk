<?php

/**
 * @bidirekt provides GET /health
 * @bidirekt response 200 Health
 *
 * @bidirekt schema Health
 */
function health() {}

/**
 * @bidirekt provides GET /ready
 * @bidirekt response 200 Ready
 *
 * @bidirekt schema Ready
 * @bidirekt field ok type=boolean
 * @bidirekt schema Unused
 */
function ready() {}

/**
 * @bidirekt provides GET /live
 * @bidirekt response 200 Live
 *
 * @bidirekt schema Live
 * @bidirekt field since
 */
function live() {}
