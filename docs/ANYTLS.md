# AnyTLS 节点

后台新增或编辑节点，类型选择 **AnyTLS（sort=16）**，节点地址填直连域名或 IP。
无需数据库结构迁移。自定义配置：

```json
{
  "protocol": "anytls",
  "offset_port_user": "443",
  "offset_port_node": "443",
  "sni": "hk-anytls.example.com",
  "allow_insecure": false,
  "udp": true
}
```

用户密码为 UUID，节点服务端必须同步相同 UUID。WebAPI 对 sort=16 保留 UUID，
按现有节点权限、到期、流量限制过滤用户，流量通过原 `/mod_mu/users/traffic` 结算。

`/clash` 输出 AnyTLS 节点（适用于支持该协议的 Shadowrocket/mihomo），`/singbox`
输出 AnyTLS outbound，`/anytls` 输出独立 URI 订阅，`/v2ray` 也包含 AnyTLS URI。
Xray/V2Ray JSON 不支持 AnyTLS，因此 `/v2rayjson` 不输出这些节点。

开启证书校验，SNI 与服务端可信证书域名相同；Cloudflare 普通橙云不能承载原生
AnyTLS，DNS 应为灰云。NAT 的客户端端口填 offset_port_user，本机端口填
offset_port_node，并在 native-manager 中设置相同监听端口。

服务端安装见节点适配器仓库 `docs/ANYTLS.md`。AnyTLS 增强协议伪装不代表 IP
不会被封；部署在已经无法直连的 IP 上不会恢复该 IP 的可达性。
