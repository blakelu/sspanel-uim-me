<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use App\Services\Subscribe;
use App\Utils\Tools;
use function array_filter;
use function array_merge;
use function json_decode;
use function json_encode;

final class SingBox extends Base
{
    public function getContent($user): string
    {
        $nodes = [];
        $singbox_config = $_ENV['SingBox_Config'];
        $nodes_raw = Subscribe::getUserNodes($user);

        foreach ($nodes_raw as $node_raw) {
            $node_custom_config = json_decode($node_raw->custom_config, true);

            switch ((int) $node_raw->sort) {
                case 0:
                    $node = [
                        'type' => 'shadowsocks',
                        'tag' => $node_raw->name,
                        'server' => $node_raw->server,
                        'server_port' => (int) $user->port,
                        'method' => $user->method,
                        'password' => $user->passwd,
                    ];

                    break;
                case 1:
                    $ss_2022_port = $node_custom_config['offset_port_user'] ??
                        ($node_custom_config['offset_port_node'] ?? 443);
                    $method = $node_custom_config['method'] ?? '2022-blake3-aes-128-gcm';
                    $user_pk = Tools::genSs2022UserPk($user->passwd, $method);
                    $uot = $node_custom_config['uot'] ?? false;

                    if (! $user_pk) {
                        $node = [];
                        break;
                    }

                    $server_key = $node_custom_config['server_key'] ?? '';

                    $node = [
                        'type' => 'shadowsocks',
                        'tag' => $node_raw->name,
                        'server' => $node_raw->server,
                        'server_port' => (int) $ss_2022_port,
                        'method' => $method,
                        'password' => $server_key === '' ? $user_pk : $server_key . ':' .$user_pk,
                        'udp_over_tcp' => (bool) $uot,
                    ];

                    break;
                case 2:
                    $tuic_port = $node_custom_config['offset_port_user'] ??
                        ($node_custom_config['offset_port_node'] ?? 443);
                    $host = $node_custom_config['host'] ?? '';
                    $allow_insecure = $node_custom_config['allow_insecure'] ?? false;
                    $congestion_control = $node_custom_config['congestion_control'] ?? 'bbr';

                    $node = [
                        'type' => 'tuic',
                        'tag' => $node_raw->name,
                        'server' => $node_raw->server,
                        'server_port' => (int) $tuic_port,
                        'uuid' => $user->uuid,
                        'password' => $user->passwd,
                        'congestion_control' => $congestion_control,
                        'zero_rtt_handshake' => true,
                        'tls' => [
                            'enabled' => true,
                            'server_name' => $host,
                            'insecure' => (bool) $allow_insecure,
                        ],
                    ];

                    $node['tls'] = array_filter($node['tls']);

                    break;
                case 11:
                    $vless_config = VlessReality::getConfig($node_raw);
                    if (VlessReality::isEnabled($vless_config)) {
                        // Official sing-box has no XHTTP transport. Do not
                        // silently export an XHTTP node as raw TCP REALITY.
                        if (! VlessReality::isConfigured($vless_config) || VlessReality::isXHTTP($vless_config)) {
                            $node = [];
                            break;
                        }

                        $node = [
                            'type' => 'vless',
                            'tag' => $node_raw->name,
                            'server' => $node_raw->server,
                            'server_port' => VlessReality::getPort($vless_config),
                            'uuid' => $user->uuid,
                            'flow' => VlessReality::getFlow($vless_config),
                            'packet_encoding' => 'xudp',
                            'tls' => [
                                'enabled' => true,
                                'server_name' => VlessReality::getServerName($vless_config),
                                'utls' => [
                                    'enabled' => true,
                                    'fingerprint' => VlessReality::getFingerprint($vless_config),
                                ],
                                'reality' => [
                                    'enabled' => true,
                                    'public_key' => VlessReality::getPublicKey($vless_config),
                                    'short_id' => VlessReality::getShortID($vless_config),
                                ],
                            ],
                        ];

                        if (VlessReality::isWebSocketTLS($vless_config)) {
                            $ws_options = VlessReality::getWebSocketOptions($vless_config);
                            unset($node['flow'], $node['tls']['reality']);
                            $node['tls']['alpn'] = ['http/1.1'];
                            $node['transport'] = [
                                'type' => 'ws',
                                'path' => $ws_options['path'],
                                'headers' => $ws_options['headers'],
                            ];
                        }

                        break;
                    }

                    $v2_port = $node_custom_config['offset_port_user'] ??
                        ($node_custom_config['offset_port_node'] ?? 443);
                    $transport = ($node_custom_config['network'] ?? '') === 'tcp' ? '' : $node_custom_config['network'];
                    $host = $node_custom_config['header']['request']['headers']['Host'][0] ??
                        $node_custom_config['host'] ?? '';
                    $path = $node_custom_config['header']['request']['path'][0] ?? $node_custom_config['path'] ?? '';
                    $headers = $node_custom_config['header']['request']['headers'] ?? [];
                    $service_name = $node_custom_config['servicename'] ?? '';
                    $utls = $node_custom_config['utls'] ?? false;
                    $method = $node_custom_config['method'] ?? '';
                    $max_early_data = $node_custom_config['max_early_data'] ?? '';
                    $early_data_header_name = $node_custom_config['early_data_header_name'] ?? '';

                    $node = [
                        'type' => 'vmess',
                        'tag' => $node_raw->name,
                        'server' => $node_raw->server,
                        'server_port' => (int) $v2_port,
                        'uuid' => $user->uuid,
                        'security' => 'auto',
                        'alter_id' => 0,
                        'tls' => [
                            'enabled' => true,
                            'server_name' => $host,
                            'utls' => [
                                'enabled' => $utls,
                                'fingerprint' => 'chrome',
                            ],
                        ],
                        'packet_encoding' => 'xudp',
                        'global_padding' => true,
                        'authenticated_length' => true,
                        'transport' => [
                            'type' => $transport,
                            'path' => $path,
                            'method' => $method,
                            'headers' => $headers,
                            'service_name' => $service_name,
                            'max_early_data' => (int) $max_early_data,
                            'early_data_header_name' => $early_data_header_name,
                        ],
                    ];

                    $node['tls'] = array_filter($node['tls']);
                    $node['transport'] = array_filter($node['transport']);

                    break;
                case 14:
                    $trojan_port = $node_custom_config['offset_port_user'] ??
                        ($node_custom_config['offset_port_node'] ?? 443);
                    $host = $node_custom_config['host'] ?? '';
                    $allow_insecure = $node_custom_config['allow_insecure'] ?? '0';
                    $transport = $node_custom_config['network'] ?? '';
                    $path = $node_custom_config['header']['request']['path'][0] ?? $node_custom_config['path'] ?? '';
                    $headers = $node_custom_config['header']['request']['headers'] ?? [];
                    $service_name = $node_custom_config['servicename'] ?? '';

                    $node = [
                        'type' => 'trojan',
                        'tag' => $node_raw->name,
                        'server' => $node_raw->server,
                        'server_port' => (int) $trojan_port,
                        'password' => $user->uuid,
                        'tls' => [
                            'enabled' => true,
                            'server_name' => $host,
                            'insecure' => (bool) $allow_insecure,
                        ],
                        'transport' => [
                            'type' => $transport,
                            'path' => $path,
                            'headers' => $headers,
                            'service_name' => $service_name,
                        ],
                    ];

                    $node['tls'] = array_filter($node['tls']);
                    $node['transport'] = array_filter($node['transport']);

                    break;
                case 15:
                    $hysteria2_config = Hysteria2::getConfig($node_raw);
                    $obfs = Hysteria2::getObfs($hysteria2_config);
                    $obfs_password = Hysteria2::getObfsPassword($hysteria2_config);
                    $up_mbps = (int) ($hysteria2_config['up_mbps'] ?? 0);
                    $down_mbps = (int) ($hysteria2_config['down_mbps'] ?? 0);

                    $node = [
                        'type' => 'hysteria2',
                        'tag' => $node_raw->name,
                        'server' => $node_raw->server,
                        'server_port' => Hysteria2::getPort($hysteria2_config),
                        'password' => $user->uuid,
                        'tls' => [
                            'enabled' => true,
                            'server_name' => Hysteria2::getSNI($node_raw, $hysteria2_config),
                            'insecure' => Hysteria2::isInsecure($hysteria2_config),
                        ],
                    ];

                    if ($obfs !== '') {
                        $node['obfs'] = [
                            'type' => $obfs,
                            'password' => $obfs_password,
                        ];
                    }
                    if ($up_mbps > 0) {
                        $node['up_mbps'] = $up_mbps;
                    }
                    if ($down_mbps > 0) {
                        $node['down_mbps'] = $down_mbps;
                    }

                    break;
                case 16:
                    $anytls_config = AnyTLS::getConfig($node_raw);
                    $node = [
                        'type' => 'anytls',
                        'tag' => $node_raw->name,
                        'server' => $node_raw->server,
                        'server_port' => AnyTLS::getPort($anytls_config),
                        'password' => $user->uuid,
                        'tls' => [
                            'enabled' => true,
                            'server_name' => AnyTLS::getSNI($node_raw, $anytls_config),
                            'insecure' => AnyTLS::isInsecure($anytls_config),
                        ],
                    ];

                    break;
                default:
                    $node = [];
                    break;
            }

            if ($node === []) {
                continue;
            }

            $nodes[] = $node;
            $singbox_config['outbounds'][0]['outbounds'][] = $node_raw->name;
            $singbox_config['outbounds'][1]['outbounds'][] = $node_raw->name;
        }

        $singbox_config['outbounds'] = array_merge($singbox_config['outbounds'], $nodes);
        $singbox_config['experimental']['cache_file']['cache_id'] = $_ENV['appName'];

        return json_encode($singbox_config);
    }
}
