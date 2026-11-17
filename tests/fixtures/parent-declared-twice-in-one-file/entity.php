<?php

namespace App;

if (PHP_VERSION_ID >= 80100) {
    abstract class Entity
    {
        public string $id = '';
    }
} else {
    abstract class Entity
    {
        public string $id = '';
    }
}
