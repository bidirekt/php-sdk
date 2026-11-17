<?php

declare(strict_types=1);

namespace App\Domain;

enum PetStatus
{
    case Available;
    case Pending;
    case Sold;
}
