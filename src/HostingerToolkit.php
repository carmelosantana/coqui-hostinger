<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitHostinger;

use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;
use CarmeloSantana\CoquiToolkitHostinger\Runtime\HostingerClient;
use CarmeloSantana\CoquiToolkitHostinger\Tool\ActionTool;
use CarmeloSantana\CoquiToolkitHostinger\Tool\BackupTool;
use CarmeloSantana\CoquiToolkitHostinger\Tool\DnsTool;
use CarmeloSantana\CoquiToolkitHostinger\Tool\FirewallTool;
use CarmeloSantana\CoquiToolkitHostinger\Tool\RecoveryTool;
use CarmeloSantana\CoquiToolkitHostinger\Tool\ScriptTool;
use CarmeloSantana\CoquiToolkitHostinger\Tool\SshKeyTool;
use CarmeloSantana\CoquiToolkitHostinger\Tool\TemplateTool;
use CarmeloSantana\CoquiToolkitHostinger\Tool\VpsTool;

/**
 * Hostinger VPS management toolkit for Coqui.
 *
 * Provides comprehensive Hostinger VPS API access: virtual machines,
 * firewalls, SSH keys, backups, snapshots, recovery mode, PTR records,
 * OS templates, data centers, post-install scripts, and action history.
 */
final class HostingerToolkit implements ToolkitInterface
{
    private readonly HostingerClient $client;

    public function __construct(
        ?HostingerClient $client = null,
    ) {
        $this->client = $client ?? HostingerClient::fromEnv();
    }

    /**
     * @return array<\CarmeloSantana\PHPAgents\Contract\ToolInterface>
     */
    public function tools(): array
    {
        return [
            (new VpsTool($this->client))->build(),
            (new FirewallTool($this->client))->build(),
            (new SshKeyTool($this->client))->build(),
            (new BackupTool($this->client))->build(),
            (new RecoveryTool($this->client))->build(),
            (new DnsTool($this->client))->build(),
            (new TemplateTool($this->client))->build(),
            (new ScriptTool($this->client))->build(),
            (new ActionTool($this->client))->build(),
        ];
    }

    public function guidelines(): string
    {
        return <<<'GUIDELINES'
        <HOSTINGER-GUIDELINES>
        ## Hostinger VPS Toolkit

        You have full access to the Hostinger VPS API v1 through the following tools:

        ### Tool Overview
        | Tool | Purpose |
        |------|---------|
        | **hostinger_vps** | List/get/start/stop/restart/recreate/setup VMs, set hostname, reset root password |
        | **hostinger_firewall** | Full firewall management — create/delete firewalls, activate/deactivate on VMs, sync and CRUD firewall rules |
        | **hostinger_ssh** | Manage SSH public keys — list/create/delete keys, attach/detach to VMs |
        | **hostinger_backup** | List backups, restore from backup, create/get/restore/delete snapshots |
        | **hostinger_recovery** | Start/stop VPS recovery mode (mounts disk at /mnt for rescue) |
        | **hostinger_dns** | Create/delete PTR (reverse DNS) records for VPS IP addresses |
        | **hostinger_template** | List available OS templates and data centers |
        | **hostinger_script** | Manage post-install scripts (CRUD) — auto-run after VM setup |
        | **hostinger_actions** | View action history (audit log) for a VM |

        ### Common Workflows

        **Provision a new VPS:**
        1. `hostinger_template(action: "list_templates")` — find the OS template ID
        2. `hostinger_template(action: "list_data_centers")` — find the data center
        3. `hostinger_vps(action: "setup", vm_id: ..., template_id: ..., password: "...", hostname: "...")`

        **Secure a VPS with firewall:**
        1. `hostinger_firewall(action: "create", name: "web-server-fw")`
        2. `hostinger_firewall(action: "create_rule", firewall_id: ..., protocol: "TCP", port: "22", source: "YOUR_IP/32", source_type: "ip")` — SSH from your IP only
        3. `hostinger_firewall(action: "create_rule", firewall_id: ..., protocol: "TCP", port: "80", source_type: "anywhere")` — HTTP
        4. `hostinger_firewall(action: "create_rule", firewall_id: ..., protocol: "TCP", port: "443", source_type: "anywhere")` — HTTPS
        5. `hostinger_firewall(action: "activate", firewall_id: ..., vm_id: ...)`

        **Set up SSH key access:**
        1. `hostinger_ssh(action: "create", name: "deploy-key", public_key: "ssh-rsa AAAA...")`
        2. `hostinger_ssh(action: "attach", key_id: ..., vm_id: ...)`

        **Backup before maintenance:**
        1. `hostinger_backup(action: "create_snapshot", vm_id: ...)` — create a snapshot first
        2. Perform maintenance operations
        3. If something goes wrong: `hostinger_backup(action: "restore_snapshot", vm_id: ..., snapshot_id: ...)`

        **Deploy with post-install script:**
        1. `hostinger_script(action: "create", name: "setup-webserver", script: "#!/bin/bash\napt update && apt install -y nginx certbot")`
        2. `hostinger_vps(action: "recreate", vm_id: ..., password: "...", template_id: ..., post_install_script_id: ...)`

        ### Important Notes
        - All IDs (vm_id, firewall_id, key_id, etc.) are integers
        - After modifying firewall rules, use `sync` to push changes to VMs
        - Creating a new snapshot overwrites any existing snapshot for that VM
        - Restoring a backup or snapshot OVERWRITES all existing data — always confirm first
        - Post-install scripts run as root and have a 48KB size limit
        - Recovery mode mounts the original disk at /mnt — useful for fixing boot issues
        - Destructive operations (recreate, restore, delete, set_password) require user confirmation
        </HOSTINGER-GUIDELINES>
        GUIDELINES;
    }
}
