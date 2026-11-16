<?php

namespace Acme\Model;

enum Visibility: string
{
    case Public = 'public';
    case Hidden = 'hidden';
}
