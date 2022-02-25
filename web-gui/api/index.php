<?php

ob_start();

$config = base64_decode(file_get_contents('php://input'));
$wglist = parse_ini_string($config, true);

include 'wg.php';

$log = ob_get_clean();

$url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['DOCUMENT_URI']);

// 生成安装脚本

foreach ($wglist as &$peer) {
    $peer['sh'] = "wget -qO- {$url}/{$peer['dir']}/alpine | sh -";
}

echo json_encode(compact('log', 'wglist'));
