<?php

declare(strict_types=1);

namespace App;

enum PetStatus: string
{
    case Available = 'available';
    case Pending = 'pending';
    case Sold = 'sold';
}
