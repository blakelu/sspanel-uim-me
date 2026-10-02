<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use App\Services\Subscribe;
use App\Utils\Tools;
use function array_merge;
use function json_decode;
use function yaml_emit;
use const YAML_UTF8_ENCODING;

final class Clash extends Base
{
    public function getContent($user): string
    {
        $nodes = [];
        $clash_config = $_ENV['Clash_Config'];
        $clash_group_indexes = $_ENV['Clash_Group_Indexes'];
        $clash_group_config = $_ENV['Clash_Group_Config'];
        $nodes_raw = Subscribe::getUserNodes($user);

        foreach ($nodes_raw as $node_raw) {
            $node_custom_config = json_decode($node_raw->custom_config, true);

            switch ((int) $node_raw->sort) {
                case 0:
                    $plugin = $node_custom_config['plugin'] ?? '';
                    $plugin_option = $node_custom_config['plugin_option'] ?? null;
                    // Clash 特定配置
                    $udp = $node_custom_config['udp'] ?? true;

                    $node = [
                        'name' => $node_raw->name,
                        'type' => 'ss',
                        'server' => $node_raw->server,
                        'port' => (int) $user->port,
                        'password' => $user->passwd,
                        'cipher' => $user->method,
                        'udp' => (bool) $udp,
                        'plugin' => $plugin,
                        'plugin-opts' => $plugin_option,
                    ];

                    break;
                case 1:
                    $ss_2022_port = $node_custom_config['offset_port_user'] ??
                        ($node_custom_config['offset_port_node'] ?? 443);
                    $method = $node_custom_config['method'] ?? '2022-blake3-aes-128-gcm';
                    $user_pk = Tools::genSs2022UserPk($user->passwd, $method);

                    if (! $user_pk) {
                        $node = [];
                        break;
                    }

                    // Clash 特定配置
                    $udp = $node_custom_config['udp'] ?? true;
                    $server_key = $node_custom_config['server_key'] ?? '';
                    $uot = $node_custom_config['uot'] ?? false;

                    $node = [
                        'name' => $node_raw->name,
                        'type' => 'ss',
                        'server' => $node_raw->server,
                        'port' => (int) $ss_2022_port,
                        'password' => $server_key === '' ? $user_pk : $server_key . ':' .$user_pk,
                        'cipher' => $method,
                        'udp' => (bool) $udp,
                        'udp_over_tcp' => (bool) $uot,
                    ];

                    break;
                case 2:
                    $tuic_port = $node_custom_config['offset_port_user'] ??
                        ($node_custom_config['offset_port_node'] ?? 443);
                    $host = $node_custom_config['host'] ?? '';
                    $congestion_control = $node_custom_config['congestion_control'] ?? 'bbr';
                    // Only Clash.Meta core has TUIC support
                    // Tuic V5 Only
                    $node = [
                        'name' => $node_raw->name,
                        'type' => 'tuic',
                        'server' => $node_raw->server,
                        'port' => (int) $tuic_port,
                        'password' => $user->passwd,
                        'uuid' => $user->uuid,
                        'sni' => $host,
                        'congestion-controller' => $congestion_control,
                        'reduce-rtt' => true,
                    ];

                    break;
                case 11:
                    $vless_config = VlessReality::getConfig($node_raw);
                    if (VlessReality::isEnabled($vless_config)) {
                        if (! VlessReality::isConfigured($vless_config)) {
                            $node = [];
                            break;
                        }

                        $node = [
                            'name' => $node_raw->name,
                            'type' => 'vless',
                            'server' => $node_raw->server,
                            'port' => VlessReality::getPort($vless_config),
                            'uuid' => $user->uuid,
                            'network' => VlessReality::getNetwork($vless_config),
                            'tls' => true,
                            'udp' => (bool) ($vless_config['udp'] ?? true),
                            'flow' => VlessReality::getFlow($vless_config),
                            'servername' => VlessReality::getServerName($vless_config),
                            'client-fingerprint' => VlessReality::getFingerprint($vless_config),
                            'reality-opts' => [
                                'public-key' => VlessReality::getPublicKey($vless_config),
                                'short-id' => VlessReality::getShortID($vless_config),
                            ],
                        ];

                        if (VlessReality::isWebSocketTLS($vless_config)) {
                            unset($node['flow'], $node['reality-opts']);
                            $node['ws-opts'] = VlessReality::getWebSocketOptions($vless_config);
                            $node['alpn'] = ['http/1.1'];
                        }

                        break;
                    }

                    $v2_port = $node_custom_config['offset_port_user'] ??
                        ($node_custom_config['offset_port_node'] ?? 443);
                    $security = $node_custom_config['security'] ?? 'none';
                    $encryption = $node_custom_config['encryption'] ?? 'auto';
                    $network = $node_custom_config['network'] ?? '';
                    $host = $node_custom_config['header']['request']['headers']['Host'][0] ??
                        $node_custom_config['host'] ?? '';
                    $allow_insecure = $node_custom_config['allow_insecure'] ?? false;
                    $tls = $security === 'tls';
                    // Clash 特定配置
                    $udp = $node_custom_config['udp'] ?? true;
                    $ws_opts = $node_custom_config['ws-opts'] ?? $node_custom_config['ws_opts'] ?? null;
                    $h2_opts = $node_custom_config['h2-opts'] ?? $node_custom_config['h2_opts'] ?? null;
                    $http_opts = $node_custom_config['http-opts'] ?? $node_custom_config['http_opts'] ?? null;
                    $grpc_opts = $node_custom_config['grpc-opts'] ?? $node_custom_config['grpc_opts'] ?? null;
                    // HTTPUpgrade 在 Clash.Meta 内核中属于 ws 类型
                    if ($network === 'httpupgrade') {
                        $network = 'ws';
                    }

                    $node = [
                        'name' => $node_raw->name,
                        'type' => 'vmess',
                        'server' => $node_raw->server,
                        'port' => (int) $v2_port,
                        'uuid' => $user->uuid,
                        'alterId' => 0,
                        'cipher' => $encryption,
                        'udp' => (bool) $udp,
                        'tls' => $tls,
                        'skip-cert-verify' => (bool) $allow_insecure,
                        'servername' => $host,
                        'network' => $network,
                        'ws-opts' => $ws_opts,
                        'h2-opts' => $h2_opts,
                        'http-opts' => $http_opts,
                        'grpc-opts' => $grpc_opts,
                    ];

                    break;
                case 14:
                    $trojan_port = $node_custom_config['offset_port_user'] ??
                        ($node_custom_config['offset_port_node'] ?? 443);
                    $network = $node_custom_config['header']['type'] ?? $node_custom_config['network'] ?? 'tcp';
                    $host = $node_custom_config['host'] ?? '';
                    $allow_insecure = $node_custom_config['allow_insecure'] ?? false;
                    // Clash 特定配置
                    $udp = $node_custom_config['udp'] ?? true;
                    $ws_opts = $node_custom_config['ws-opts'] ?? $node_custom_config['ws_opts'] ?? null;
                    $grpc_opts = $node_custom_config['grpc-opts'] ?? $node_custom_config['grpc_opts'] ?? null;
                    // HTTPUpgrade 在 Clash.Meta 内核中属于 ws 类型
                    if ($network === 'httpupgrade') {
                        $network = 'ws';
                    }

                    $node = [
                        'name' => $node_raw->name,
                        'type' => 'trojan',
                        'server' => $node_raw->server,
                        'sni' => $host,
                        'port' => (int) $trojan_port,
                        'password' => $user->uuid,
                        'network' => $network,
                        'udp' => (bool) $udp,
                        'skip-cert-verify' => (bool) $allow_insecure,
                        'ws-opts' => $ws_opts,
                        'grpc-opts' => $grpc_opts,
                    ];

                    break;
                case 15:
                    $hysteria2_config = Hysteria2::getConfig($node_raw);
                    $obfs = Hysteria2::getObfs($hysteria2_config);
                    $obfs_password = Hysteria2::getObfsPassword($hysteria2_config);
                    $up_mbps = (int) ($hysteria2_config['up_mbps'] ?? 0);
                    $down_mbps = (int) ($hysteria2_config['down_mbps'] ?? 0);

                    $node = [
                        'name' => $node_raw->name,
                        'type' => 'hysteria2',
                        'server' => $node_raw->server,
                        'port' => Hysteria2::getPort($hysteria2_config),
                        'password' => $user->uuid,
                        'sni' => Hysteria2::getSNI($node_raw, $hysteria2_config),
                        'skip-cert-verify' => Hysteria2::isInsecure($hysteria2_config),
                    ];

                    if ($obfs !== '') {
                        $node['obfs'] = $obfs;
                        if ($obfs_password !== '') {
                            $node['obfs-password'] = $obfs_password;
                        }
                    }
                    if ($up_mbps > 0) {
                        $node['up'] = $up_mbps . ' Mbps';
                    }
                    if ($down_mbps > 0) {
                        $node['down'] = $down_mbps . ' Mbps';
                    }

                    break;
                default:
                    $node = [];
                    break;
            }

            if ($node === []) {
                continue;
            }

            $nodes[] = $node;

            foreach ($clash_group_indexes as $index) {
                $clash_group_config['proxy-groups'][$index]['proxies'][] = $node_raw->name;
            }
        }

        $clash_nodes = [
            'proxies' => $nodes,
        ];

        return yaml_emit(
            array_merge($clash_config, $clash_nodes, $clash_group_config),
            YAML_UTF8_ENCODING
        );
    }
}
