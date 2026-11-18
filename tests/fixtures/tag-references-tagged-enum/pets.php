<?php

/** @bidirekt schema name=PetStatus */
enum Status: string
{
    case Available = 'available';
    case Sold = 'sold';
}

/**
 * @bidirekt provides GET /pets/status
 * @bidirekt response 200 PetStatus
 */
function findStatus(): Status
{
    return Status::Available;
}
