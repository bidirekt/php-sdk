<?php

namespace App;

final class PetLabels
{
    // Labels shown next to each status in the admin, one per enum
    ////////////////////////////////////////////////////////////////////////////
}

enum PetStatus: string
{
    case Available = 'available';
    case Sold = 'sold';
}
