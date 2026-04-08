# Hostinger VPS Toolkit for Coqui

Comprehensive Hostinger VPS management toolkit for [Coqui](https://github.com/carmelosantana/coqui). Provides 9 tools covering the full Hostinger VPS REST API v1 — virtual machines, firewalls, SSH keys, backups, snapshots, recovery mode, DNS PTR records, OS templates, and post-install scripts.

## Requirements

- PHP 8.4+
- [Coqui](https://github.com/carmelosantana/coqui) with `carmelosantana/php-agents` ^0.7
- A [Hostinger API token](https://developers.hostinger.com)

## Installation

```bash
composer require coquibot/coqui-toolkit-hostinger
```

Coqui auto-discovers the toolkit on next boot. No code changes needed.

## Credential Setup

On first use, any tool call will prompt you to set the API token:

```
credentials(action: "set", key: "HOSTINGER_API_TOKEN", value: "your-token-here")
```

Generate a token at [developers.hostinger.com](https://developers.hostinger.com).

## Tools

| Tool | Actions | Description |
|------|---------|-------------|
| `hostinger_vps` | list, get, start, stop, restart, recreate, setup, set_hostname, set_password | Virtual machine lifecycle management |
| `hostinger_firewall` | list, get, create, delete, activate, deactivate, sync, list_rules, create_rule, update_rule, delete_rule | Firewall and firewall rule management |
| `hostinger_ssh` | list, create, delete, attach, detach | SSH public key management |
| `hostinger_backup` | list_backups, restore_backup, create_snapshot, get_snapshot, restore_snapshot, delete_snapshot | Backup and snapshot management |
| `hostinger_recovery` | start, stop | VPS recovery mode (rescue disk) |
| `hostinger_dns` | create_ptr, delete_ptr | Reverse DNS (PTR) records |
| `hostinger_template` | list_templates, list_data_centers | OS templates and data center listing |
| `hostinger_script` | list, get, create, update, delete | Post-install script management |
| `hostinger_actions` | list | VM action history (audit log) |

## Gated Operations

Destructive operations require user confirmation (bypassed with `--auto-approve`):

| Tool | Gated Actions |
|------|---------------|
| `hostinger_vps` | recreate, setup, set_password |
| `hostinger_firewall` | delete, deactivate, delete_rule |
| `hostinger_ssh` | delete, detach |
| `hostinger_backup` | restore_backup, restore_snapshot, delete_snapshot |
| `hostinger_recovery` | start, stop |
| `hostinger_script` | delete |

## Usage Examples

**List all VPS instances:**
```
hostinger_vps(action: "list")
```

**Provision a new VPS:**
```
hostinger_template(action: "list_templates")
hostinger_vps(action: "setup", vm_id: 123, template_id: 456, password: "SecurePass!", hostname: "web-01.example.com")
```

**Secure with a firewall:**
```
hostinger_firewall(action: "create", name: "web-fw")
hostinger_firewall(action: "create_rule", firewall_id: 10, protocol: "TCP", port: "22", source: "YOUR_IP/32", source_type: "ip")
hostinger_firewall(action: "create_rule", firewall_id: 10, protocol: "TCP", port: "443", source_type: "anywhere")
hostinger_firewall(action: "activate", firewall_id: 10, vm_id: 123)
```

**SSH key deployment:**
```
hostinger_ssh(action: "create", name: "deploy-key", public_key: "ssh-rsa AAAA...")
hostinger_ssh(action: "attach", key_id: 5, vm_id: 123)
```

**Snapshot before maintenance:**
```
hostinger_backup(action: "create_snapshot", vm_id: 123)
hostinger_backup(action: "restore_snapshot", vm_id: 123, snapshot_id: 789)
```

## Architecture

```
src/
├── HostingerToolkit.php          # ToolkitInterface — registers all 9 tools
├── Runtime/
│   ├── HostingerClient.php       # Thin HTTP wrapper for the Hostinger API
│   └── HostingerResult.php       # Typed value object for API responses
└── Tool/
    ├── ActionTool.php            # hostinger_actions
    ├── BackupTool.php            # hostinger_backup
    ├── DnsTool.php               # hostinger_dns
    ├── FirewallTool.php          # hostinger_firewall
    ├── RecoveryTool.php          # hostinger_recovery
    ├── ScriptTool.php            # hostinger_script
    ├── SshKeyTool.php            # hostinger_ssh
    ├── TemplateTool.php          # hostinger_template
    └── VpsTool.php               # hostinger_vps
```

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Static analysis
composer analyse
```

## License

MIT
