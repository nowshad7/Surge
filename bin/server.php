#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Nsd7\RealtimeQueue\Server;

$server = new Server();
$server->start();       