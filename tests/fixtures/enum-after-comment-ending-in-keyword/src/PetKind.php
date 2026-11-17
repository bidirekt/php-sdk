<?php

namespace App;

// The kind of a pet, one value per animal class
#[Deprecated]
enum PetKind: string
{
    case Cat = 'cat';
    case Dog = 'dog';
}
