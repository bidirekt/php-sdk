<?php

/** @bidirekt schema name=Priority */
enum Level: int
{
    case Low = 1;
    case High = 2;
}

/** @bidirekt schema */
enum Blank
{
    case Nothing;
}

/** @bidirekt schema */
enum Status: string
{
    case Open = 'open';
}
