<?php

namespace Acme\Shared;

/**
 * @bidirekt schema name=PetSize
 */
enum Size: string
{
    case Small = 'small';
    case Large = 'large';
}
