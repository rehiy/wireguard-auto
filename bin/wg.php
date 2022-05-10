<?php

/**
 * WireGuard 自动配置
 * @Author rehiy <wang@rehiy.com>
 * @Website https://www.rehiy.com
 */

echo "Create WireGuard Config Files \n";

if (empty($wglist) || !is_array($wglist) || count($wglist) < 3) {
    exit('Config Error !');
}

$global = array();
if (isset($wglist['global'])) {
    $global = array_shift($wglist);
}

$did = wg_dir(null);
$ini = 'deploy/' . $did . '.config.ini';

wg_vip(isset($global['vip']) ? $global['vip'] : '172.21.0.1/22');

/////////////////////////////////////////////////////////////////

foreach ($wglist as $name => &$node) {
    echo "Create Key Pair for $name \n";
    isset($node['name']) || ($node['name'] = $name);
    isset($node['vip']) || ($node['vip'] = wg_vip());
    isset($node['port']) || ($node['port'] = $global['port']);
    isset($node['alive']) || ($node['alive'] = $global['alive']);
    isset($node['acl']) || ($node['acl'] = $global['acl']);
    if (empty($node['peers'])) {
        if (empty($global['peers'])) {
            $node['peers'] = array_keys($wglist);
        } else {
            $node['peers'] = $global['peers'];
        }
    }
    if (empty($node['acl'])) {
        $node['acl'] = preg_replace('/\d+$/', '32', $node['vip']);
    }
    if (empty($node['pri_key']) || empty($node['pub_key'])) {
        $node += wg_key();
    }
    $node['dir'] = wg_dir($node);
}

foreach ($wglist as &$serve) {
    echo "Create Deploy Files for {$serve['name']} \n";
    $conf = array();
    $conf[] = wg_config_interface($serve);
    foreach ($wglist as $peer) {
        if ($serve != $peer && in_array($peer['name'], $serve['peers'])) {
            $conf[] = wg_config_peer($peer);
        }
    }
    $_conf = implode("\n\n", $conf);
    $_start = wg_start_script($serve);
    file_put_contents($serve['dir'] . '/wg0.conf', $_conf);
    file_put_contents($serve['dir'] . '/wg0.start', $_start);
    wg_deploy_scripts($serve['dir'], $_conf, $_start);
}

put_ini_file($wglist, $ini);

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
        return $did = dechex(
            crc32($_SERVER['HTTP_X_FORWARDED_FOR'] . $_SERVER['REMOTE_ADDR'])
        );
    } else {
        $pre = $did != '0' ? $did : $peer['name'];
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
    $conf[] = '#!/bin/sh';
    $conf[] = '';
    $conf[] = 'sysctl -w net.ipv4.ip_forward=1';
    $conf[] = '';
    $conf[] = 'ip link add dev wg0 type wireguard';
    $conf[] = 'ip address add dev wg0 ' . $serve['vip'];
    $conf[] = 'wg setconf wg0 /etc/wireguard/wg0.conf';
    $conf[] = 'ip link set up dev wg0';
    $conf[] = '';
    $conf[] = 'if type iptables >/dev/null; then';
    $conf[] = '  iptables -A FORWARD -i wg0 -j ACCEPT';
    $conf[] = '  iptables -A FORWARD -o wg0 -j ACCEPT';
    $conf[] = '  iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE';
    $conf[] = 'fi';
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

/////////////////////////////////////////////////////////////////

function put_ini_file($array, $file, $pk = '', $dp = 0)
{
    $str = '';
    $slf = __FUNCTION__;
    foreach ($array as $k => $v) {
        if (is_array($v)) {
            if ($dp == 0) {
                $str .= "\n[{$k}]\n\n";
                $str .= $slf($v, '', '', $dp + 1);
            } else {
                $str .= $slf($v, '', $k, $dp + 1);
            }
        } else {
            if (strpos($v, '=')) {
                $v = '"' . $v . '"';
            }
            if ($pk) {
                $str .= "{$pk}[{$k}] = {$v}\n";
            } else {
                $str .= "{$k} = {$v}\n";
            }
        }
    }
    if ($file) {
        return file_put_contents($file, trim($str));
    } else {
        return $str;
    }
}
