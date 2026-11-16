<?php

$vendorDir = dirname(__DIR__);
$baseDir = dirname($vendorDir);

return array(
    'Carbon\\' => array($vendorDir . '/nesbot/carbon/src/Carbon'),
    'Doctrine\\Common\\Collections\\' => array($vendorDir . '/doctrine/collections/src'),
    'App\\' => array($baseDir . '/src'),
);
