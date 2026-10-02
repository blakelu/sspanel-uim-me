# VLESS 节点订阅

节点类型选择 V2Ray（`sort=11`），用户 UUID 作为 VLESS 凭据。自定义配置必须保留
`"protocol": "vless"`；删掉该字段会使用 VMess 订阅分支，无法连接 VLESS 服务端。

## WebSocket + TLS / Cloudflare 橙云

节点连接地址填写橙云域名，如 `de2.cf.yepgoods.com`。自定义配置示例：

```json
{
  "protocol": "vless",
  "offset_port_user": 443,
  "offset_port_node": 443,
  "network": "ws",
  "security": "tls",
  "sni": "de2.cf.yepgoods.com",
  "host": "de2.cf.yepgoods.com",
  "path": "/vless",
  "fingerprint": "chrome",
  "udp": true
}
```

`host`、`path` 必须与服务端 WS 配置一致，也兼容 `ws-opts`、`ws_opts` 和
`wsSettings` 中的 `path` / `headers.Host`。WS/TLS 不使用 REALITY 的 `public_key`、
`short_id` 或 Vision flow；导出时会省略这些字段。

客户端连接 Cloudflare 的 `443`；源站必须启用 TLS，Cloudflare 使用 Full (strict)。
回源端口不是 443 时，用 Origin Rule 指定源站公网端口；源站监听端口另由节点配置。
面板字段不会自动创建 Cloudflare 规则或修改节点监听端口。

Cloudflare 边缘证书与源站证书分别负责客户端和回源连接。源站的自动 ACME 证书
不能修复边缘证书缺失。普通 Universal SSL 只覆盖 Zone 根域和一层子域名；使用
多层子域名时需确认边缘证书覆盖该完整域名。[Cloudflare 覆盖范围说明](https://developers.cloudflare.com/ssl/edge-certificates/universal-ssl/limitations/)。

## REALITY

现有 REALITY 配置继续可用。`network` 和 `security` 未设置时，默认 TCP + REALITY；
仍需填写 `sni`、`public_key`、`short_id`，flow 默认 `xtls-rprx-vision`。

## 订阅输出

- Shadowrocket / VLESS URI：`/sub/{token}/vless`
- V2Ray 混合 URI：`/sub/{token}/v2ray`
- Clash / Mihomo：`/sub/{token}/clash`
- Sing-box：`/sub/{token}/singbox`
- V2Ray JSON：`/sub/{token}/v2rayjson`

发布面板修复后，将节点地址和自定义配置同步到新域名，再刷新客户端订阅。
