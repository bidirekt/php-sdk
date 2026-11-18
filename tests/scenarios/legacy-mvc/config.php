<?php

define('DB_DSN', getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=petstore');
define('DB_USER', getenv('DB_USER') ?: 'petstore');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
