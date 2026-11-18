<?php

namespace App;

// The status of a pet, sent to the API as a string enum
enum PetStatus: string
{
    case Available = 'available';
    case Sold = 'sold';
}
