<?php

/**
 * WireGuard 自动配置
 * @Author rehiy <wang@rehiy.com>
 * @Website https://www.rehiy.com
 */

echo "Create WireGuard Config Files \n";

$wglist = parse_ini_file('config.ini', true);

$global = array_shift($wglist);

wg_vip($global['vip']);

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
    $dir = 'deploy/' . $serve['ip'];
    is_dir($dir) || mkdir($dir, 0755, true);
    file_put_contents($dir . '/wg0.conf', implode("\n\n", $conf));
    file_put_contents($dir . '/wg0.start', wg_start_script($serve));
}

/////////////////////////////////////////////////////////////////

function wg_key()
{
    $pri_key = exec('wg genkey');
    $pub_key = exec('echo ' . $pri_key . ' | wg pubkey');
    return compact('pri_key', 'pub_key');
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
    $conf[] = '#!/bin/sh';
    $conf[] = 'echo 1 > /proc/sys/net/ipv4/ip_forward';
    $conf[] = 'ip link add dev wg0 type wireguard';
    $conf[] = 'wg setconf wg0 /etc/wireguard/wg0.conf';
    $conf[] = 'ip address add dev wg0 ' . $serve['vip'];
    $conf[] = 'ip link set up dev wg0';
    return implode("\n", $conf);
}
