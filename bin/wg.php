<?php

/**
 * WireGuard 自动配置
 * @Author rehiy <wang@rehiy.com>
 * @Website https://www.rehiy.com
 */

echo "Create WireGuard Config Files \n";

if (empty($wglist) || !is_array($wglist) || count($wglist) < 3) {
    exit("Config Error !");
}

$global = array_shift($wglist);

wg_vip($global['vip']);

$did = wg_dir(null);

/////////////////////////////////////////////////////////////////

foreach ($wglist as $name => &$node) {
    echo "Create Key Pair for $name \n";
    isset($node['name']) || $node['name'] = $name;
    isset($node['vip']) || $node['vip'] = wg_vip();
    isset($node['port']) || $node['port'] = $global['port'];
    isset($node['alive']) || $node['alive'] = $global['alive'];
    isset($node['acl']) || $node['acl'] = $global['acl'];
    if (empty($node['acl'])) {
        $node['acl'] = preg_replace('/\d+$/', '32', $node['vip']);
    }
    $node['dir'] = wg_dir($node);
    $node += wg_key();
}

foreach ($wglist as &$serve) {
    echo "Create Deploy Files for {$serve['name']} \n";
    $conf = array();
    $conf[] = wg_config_interface($serve);
    foreach ($wglist as $peer) {
        if ($serve != $peer) {
            $conf[] = wg_config_peer($peer);
        }
    }
    $_conf = implode("\n\n", $conf);
    $_start = wg_start_script($serve);
    file_put_contents($serve['dir'] . '/wg0.conf', $_conf);
    file_put_contents($serve['dir'] . '/wg0.start', $_start);
    wg_deploy_scripts($serve['dir'], $_conf, $_start);
}

/////////////////////////////////////////////////////////////////

function wg_key()
{
    $pri_key = exec('wg genkey');
    $pub_key = exec('echo ' . $pri_key . ' | wg pubkey');
    return compact('pri_key', 'pub_key');
}

function wg_dir($peer)
{
    static $did;
    if ($peer == null) {
        return $did = dechex(crc32(
            $_SERVER['HTTP_X_FORWARDED_FOR'] . $_SERVER['REMOTE_ADDR']
        ));
    } else {
        $pre =  $did != '0' ? $did : $peer['name'];
        $dir = 'deploy/' . $pre . '-' . $peer['ip'];
        is_dir($dir) || mkdir($dir, 0755, true);
        return $dir;
    }
}

function wg_vip($cidr = '')
{
    static $vip, $mask;
    if ($cidr) {
        list($vip, $mask) = explode('/', $cidr);
        $vip = ip2long($vip);
    } else {
        return long2ip(++$vip) . '/' . $mask;
    }
}

function wg_config_interface($serve)
{
    $conf = array();
    $conf[] = '[Interface]';
    $conf[] = 'PrivateKey = ' . $serve['pri_key'];
    $conf[] = 'ListenPort = ' . $serve['port'];
    return implode("\n", $conf);
}

function wg_config_peer($peer)
{
    $conf = array();
    $conf[] = '[Peer]';
    $conf[] = 'PublicKey  = ' . $peer['pub_key'];
    $conf[] = 'Endpoint   = ' . $peer['ip'] . ':' . $peer['port'];
    $conf[] = 'AllowedIPs = ' . $peer['acl'];
    $conf[] = 'PersistentKeepalive = ' . $peer['alive'];
    return implode("\n", $conf);
}

function wg_start_script($serve)
{
    $conf = array();
    $conf[] = '#!/bin/sh' . "\n";
    $conf[] = 'echo 1 > /proc/sys/net/ipv4/ip_forward' . "\n";
    $conf[] = 'ip link add dev wg0 type wireguard';
    $conf[] = 'wg setconf wg0 /etc/wireguard/wg0.conf';
    $conf[] = 'ip address add dev wg0 ' . $serve['vip'];
    $conf[] = 'ip link set up dev wg0';
    return implode("\n", $conf);
}

function wg_deploy_scripts($dir, $conf, $start)
{
    foreach (glob('template/*') as $tpl) {
        $sc = file_get_contents($tpl);
        $sc = str_replace('{CONF}', $conf, $sc);
        $sc = str_replace('{START}', $start, $sc);
        file_put_contents($dir . '/' . basename($tpl), $sc);
    }
}
