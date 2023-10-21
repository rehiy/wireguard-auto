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

wg_vip(isset($global['wg0_ip']) ? $global['wg0_ip'] : '172.21.0.1/22');

/////////////////////////////////////////////////////////////////

foreach ($wglist as $name => &$node) {
    echo "Create Key Pair for $name \n";
    empty($node['name']) && ($node['name'] = $name);
    empty($node['port']) && ($node['port'] = $global['port']);
    empty($node['wg0_ip']) && ($node['wg0_ip'] = wg_vip());
    isset($node['alive']) || ($node['alive'] = $global['alive']);
    isset($node['allow']) || ($node['allow'] = $global['allow']);
    if (empty($node['peers'])) {
        if (empty($global['peers'])) {
            $node['peers'] = array_keys($wglist);
        } else {
            $node['peers'] = $global['peers'];
        }
    }
    if (empty($node['allow'])) {
        $node['allow'] = preg_replace('/\d+$/', '32', $node['wg0_ip']);
    } else {
        $node['allow'] = preg_replace('/\d+$/', '32', $node['wg0_ip']) . "," . $node['allow'];
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
        if ($serve != $peer && in_array($peer['name'], $serve['peers']) && in_array($serve['name'], $peer['peers'])) {
            $conf[] = wg_config_peer($peer, $serve['group'] == $peer['group']);
        }
    }
    $_conf = implode("\n\n", $conf);
    wg_deploy_scripts($serve, $_conf);
    file_put_contents($serve['dir'] . '/wg0.conf', $_conf);
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
        $vip = $peer['nat_ip'] ? $peer['nat_ip'] : $peer['lan_ip'];
        $dir = 'deploy/' . $pre . ($vip ? '-' . $vip : '');
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

function wg_config_peer($peer, $lan)
{
    $conf = array();
    $conf[] = '[Peer]';
    $conf[] = 'PublicKey  = ' . $peer['pub_key'];
    if ($lan && $peer['lan_ip']) {
        $conf[] = 'Endpoint   = ' . $peer['lan_ip'] . ':' . $peer['port'];
    } else if ($peer['nat_ip']) {
        $conf[] = 'Endpoint   = ' . $peer['nat_ip'] . ':' . $peer['port'];
    }
    if ($peer['alive']) {
        $conf[] = 'PersistentKeepalive = ' . $peer['alive'];
    }
    if ($peer['allow']) {
        $conf[] = 'AllowedIPs = ' . $peer['allow'];
    }
    return implode("\n", $conf);
}

function wg_deploy_scripts($serve, $_conf)
{
    foreach (glob('template/*') as $tpl) {
        $sc = file_get_contents($tpl);
        $sc = str_replace('{{CONF}}', $_conf, $sc);
        $sc = str_replace('{{VIP}}', $serve['wg0_ip'], $sc);
        file_put_contents($serve['dir'] . '/' . basename($tpl), $sc);
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
    if ($file === '') {
        return $str;
    }
    file_put_contents($file, trim($str));
}
