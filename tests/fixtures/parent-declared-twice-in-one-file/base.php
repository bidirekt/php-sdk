<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;

if (PHP_VERSION_ID >= 80100) {
    abstract class Base extends Entity
    {
        #[BidirektField]
        public string $kind = '';
    }
} else {
    abstract class Base extends Entity
    {
        #[BidirektField]
        public string $kind = '';
    }
}
