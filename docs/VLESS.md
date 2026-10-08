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

## 橙云 ECH 与连接地址

如果客户端到 Cloudflare 的 TLS 因域名/SNI 干扰失败，可对 WS/TLS 节点启用 ECH。
当前 Clash 订阅支持下面的附加配置，Shadowrocket 更新 Clash 订阅时也会导入：

```json
{
  "connect_address": "104.21.42.5",
  "ech_auto": true,
  "ech-opts": {
    "enable": true,
    "config": "替换为域名 HTTPS DNS 记录中的 base64 ECHConfigList"
  }
}
```

`connect_address` 可省略；填写时仅替换客户端连接地址，原 `sni` 和 WS Host 保持不变，
不修改 Cloudflare DNS 的源站地址。示例 IP 必须按实际网络可达性选择。
`ech_auto` 在生成订阅时通过 Cloudflare DoH 查询域名的 HTTPS 记录，获取当前公开
ECH 配置；Cloudflare 查询失败或返回无效配置时，再查询 Google DoH。每个来源最多
等待 1.2 秒，TLS 证书正常校验。使用面板现有 Redis 保存最近成功值：60 秒以内直接
复用，过期后重新查询；两个来源都失败时使用 24 小时以内的最近成功值，再退回
节点中保存的备用公钥。缓存故障不阻止订阅生成，同一次订阅请求对相同域名只
解析一次。未启用 ECH 的节点不会发起查询。缓存和备用值仍可能因 ECH 密钥轮换失效。

### 通过 DNS 统一维护连接入口

`connect_address` 也支持独立连接域名，例如 `de-edge.cf.example.com`。为它创建
**灰云、仅 DNS 的 A 记录**，指向已验证能完成 ECH、WebSocket 和实际 VLESS
代理请求的 Cloudflare IPv4，TTL 可设为 60 秒。源站域名继续橙云，SNI 和 WS Host
继续填写源站域名；入口域名不能拿来替换 SNI、Host，也不能指向源站 IP。

每台节点可以使用独立入口域名和不同 CF IP，避免所有节点同时依赖一个固定 IP。
用户首次更新订阅后，后续更换入口只需调整相应 A 记录，客户端在 DNS 缓存更新并
重新连接后使用新地址，通常无需再次更新订阅。DNS 缓存或应用缓存可能延长生效
时间；这不等于客户端自动故障切换，也不能消除 Cloudflare 网络整体不可达的问题。

入口 IP 必须在目标用户网络中测试，境外源站或面板测通不能证明国内用户可用。
更换入口不改变 ECH 的更新方式，ECH 配置变化仍需客户端更新订阅。其他订阅
格式尚未输出这一连接地址和 ECH 扩展，使用它们的客户端需单独验证。

启用 ECH 时 Clash 输出 `ech-opts` 并省略 `client-fingerprint`，避免 uTLS 优先导致 ECH
未协商。客户端需支持 ECH；Cloudflare 轮换配置后需要更新订阅。这不会保证域名或
Cloudflare 入口永远可达。此选项目前只作用于 Clash 订阅，其他订阅格式不受影响。

## REALITY

现有 REALITY 配置继续可用。`network` 和 `security` 未设置时，默认 TCP + REALITY；
仍需填写 `sni`、`public_key`、`short_id`，flow 默认 `xtls-rprx-vision`。

## XHTTP + REALITY

节点类型继续使用 V2Ray（`sort=11`），地址填源站 IP 或灰云域名。配置示例：

```json
{
  "protocol": "vless",
  "offset_port_user": 443,
  "offset_port_node": 443,
  "network": "xhttp",
  "security": "reality",
  "flow": "",
  "sni": "www.example.com",
  "fingerprint": "chrome",
  "public_key": "替换为原 REALITY 公钥",
  "short_id": "替换为原 REALITY Short ID",
  "path": "/xhttp",
  "mode": "auto",
  "udp": true
}
```

路径、模式、公钥和 Short ID 与服务端保持一致；可选 `host` 指定同一 XHTTP Host。
默认路径 `/xhttp`、模式 `auto`，支持 `stream-one`、`stream-up`、`packet-up`。
兼容 `splithttp` 网络别名以及 `xhttp-opts`、`xhttp_opts`、`xhttpSettings`、
`splithttpSettings` 中的基本 path/mode/host 字段。
即使误保留 `flow=xtls-rprx-vision`，导出 XHTTP 时也会省略 flow。

Clash/Mihomo 输出 `network: xhttp`、`xhttp-opts` 和原有 REALITY 密钥；
Shadowrocket 需更新至支持 XHTTP 及其 Clash 参数解析的版本。
`/vless` 和 `/v2ray` 输出携带 path/mode/host 的 XHTTP URI。
官方 sing-box 暂无 XHTTP 传输，旧 V2Ray JSON 导出也没有对应 schema，
`/singbox`、`/v2rayjson` 跳过此类节点，其他已有节点照常输出。

本配置仍为源站直连，不使用普通 Cloudflare 橙云。SNI 是 REALITY 目标域名，
不要拿它替换节点连接地址。
参考：[Xray XHTTP](https://github.com/XTLS/Xray-core/discussions/4113)、
[Mihomo XHTTP](https://wiki.metacubex.one/config/proxies/transport/#xhttp-opts)。

## 订阅输出

- Shadowrocket / VLESS URI：`/sub/{token}/vless`
- V2Ray 混合 URI：`/sub/{token}/v2ray`
- Clash / Mihomo：`/sub/{token}/clash`
- Sing-box：`/sub/{token}/singbox`
- V2Ray JSON：`/sub/{token}/v2rayjson`

发布面板修复后，将节点地址和自定义配置同步到新域名，再刷新客户端订阅。
