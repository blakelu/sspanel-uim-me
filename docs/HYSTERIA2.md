# Hysteria2 接入说明

Hysteria2 使用节点类型值 `15`，用户 UUID 作为 HTTP Auth 凭据。节点 ID 必须与
`sspanel-hy2-adapter` 的 `panel.node_id` 一致。

## 后台节点配置

进入 `/admin/node/{id}/edit`，选择 `Hysteria2`，连接地址填写证书对应的、直接解析到
HY2 服务器的域名。Cloudflare DNS 记录应使用“仅 DNS”，不能开启 CDN 代理。

推荐自定义配置：

```json
{
  "offset_port_user": 8443,
  "offset_port_node": 8443,
  "sni": "korea.hy2.example.com",
  "allow_insecure": false,
  "obfs": "",
  "obfs_password": "",
  "up_mbps": 0,
  "down_mbps": 0
}
```

- `offset_port_user`：订阅下发给客户端的公网 UDP 端口。
- `offset_port_node`：节点后端端口，未设置用户端口时作为回退值。
- `sni`：TLS SNI；也兼容旧字段名 `host`。
- `allow_insecure`：是否跳过证书校验，正式证书应保持 `false`。
- `obfs`、`obfs_password`：服务端启用混淆时填写，例如 `salamander`。
- `up_mbps`、`down_mbps`：可选的客户端带宽提示，`0` 表示不下发。

## 订阅

- Shadowrocket/Hysteria2 URI：`/sub/{token}/hysteria2`
- Clash/Mihomo：`/sub/{token}/clash`
- Sing-box：`/sub/{token}/singbox`

WebAPI `/mod_mu/users` 会为类型 `15` 返回用户 `id`、`uuid` 和节点限速字段，供
Hysteria2 Adapter 完成认证和流量统计映射。
