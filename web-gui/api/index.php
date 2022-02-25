<?php

ob_start();

$config = base64_decode(file_get_contents('php://input'));
$wglist = parse_ini_string($config, true);

include 'wg.php';

$log = ob_get_clean();

echo json_encode(compact('log', 'wglist'));
