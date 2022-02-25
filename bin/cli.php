<?php

if (is_file('config-prod.ini')) {
    $wglist = parse_ini_file('config-prod.ini', true);
} else {
    $wglist = parse_ini_file('config.ini', true);
}

include 'wg.php';
