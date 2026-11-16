<?php

/** @bidirekt schema name=PetStatus */
enum Status: string
{
    case Available = 'available';
    case Sold = 'sold';
}

/** @bidirekt schema */
enum Size: string
{
    case Small = 'small';
    case Large = 'large';
}
