<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitHostinger\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\CoquiToolkitHostinger\Runtime\HostingerClient;

/**
 * Manage Hostinger VPS backups and snapshots.
 *
 * Supports listing backups, restoring from backup, and full snapshot
 * lifecycle (create, get, restore, delete).
 */
final readonly class BackupTool
{
    public function __construct(
        private HostingerClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'hostinger_backup',
            description: 'Manage Hostinger VPS backups and snapshots — list backups, restore from backup, create/get/restore/delete snapshots.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: ['list_backups', 'restore_backup', 'create_snapshot', 'get_snapshot', 'restore_snapshot', 'delete_snapshot'],
                    required: true,
                ),
                new NumberParameter(
                    'vm_id',
                    'Virtual machine ID (required for all actions).',
                    required: false,
                    integer: true,
                ),
                new NumberParameter(
                    'backup_id',
                    'Backup ID (required for "restore_backup").',
                    required: false,
                    integer: true,
                ),
                new NumberParameter(
                    'snapshot_id',
                    'Snapshot ID (required for "restore_snapshot", "delete_snapshot").',
                    required: false,
                    integer: true,
                ),
            ],
            callback: fn(array $args) => $this->execute($args),
        );
    }

    /**
     * @param array<string, mixed> $args
     */
    private function execute(array $args): ToolResult
    {
        $action = trim((string) ($args['action'] ?? ''));

        return match ($action) {
            'list_backups' => $this->listBackups($args),
            'restore_backup' => $this->restoreBackup($args),
            'create_snapshot' => $this->createSnapshot($args),
            'get_snapshot' => $this->getSnapshot($args),
            'restore_snapshot' => $this->restoreSnapshot($args),
            'delete_snapshot' => $this->deleteSnapshot($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    /**
     * @param array<string, mixed> $args
     */
    private function listBackups(array $args): ToolResult
    {
        $vmId = $this->requireInt($args, 'vm_id');
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "list_backups" action.');
        }

        return $this->client->get("virtual-machines/{$vmId}/backups")
            ->toToolResultWith("Backups for VM {$vmId}:");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function restoreBackup(array $args): ToolResult
    {
        $vmId = $this->requireInt($args, 'vm_id');
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "restore_backup" action.');
        }

        $backupId = $this->requireInt($args, 'backup_id');
        if ($backupId === null) {
            return ToolResult::error('backup_id is required for the "restore_backup" action.');
        }

        return $this->client->post("virtual-machines/{$vmId}/backups/{$backupId}/restore")
            ->toToolResultWith("Backup {$backupId} restore initiated for VM {$vmId}. WARNING: This will overwrite all existing data.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function createSnapshot(array $args): ToolResult
    {
        $vmId = $this->requireInt($args, 'vm_id');
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "create_snapshot" action.');
        }

        return $this->client->post("virtual-machines/{$vmId}/snapshots")
            ->toToolResultWith("Snapshot creation initiated for VM {$vmId}. Note: creating a new snapshot overwrites any existing one.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function getSnapshot(array $args): ToolResult
    {
        $vmId = $this->requireInt($args, 'vm_id');
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "get_snapshot" action.');
        }

        return $this->client->get("virtual-machines/{$vmId}/snapshots")
            ->toToolResultWith("Snapshot for VM {$vmId}:");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function restoreSnapshot(array $args): ToolResult
    {
        $vmId = $this->requireInt($args, 'vm_id');
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "restore_snapshot" action.');
        }

        $snapshotId = $this->requireInt($args, 'snapshot_id');
        if ($snapshotId === null) {
            return ToolResult::error('snapshot_id is required for the "restore_snapshot" action.');
        }

        return $this->client->post("virtual-machines/{$vmId}/snapshots/{$snapshotId}/restore")
            ->toToolResultWith("Snapshot {$snapshotId} restore initiated for VM {$vmId}. WARNING: This will overwrite all existing data.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function deleteSnapshot(array $args): ToolResult
    {
        $vmId = $this->requireInt($args, 'vm_id');
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "delete_snapshot" action.');
        }

        $snapshotId = $this->requireInt($args, 'snapshot_id');
        if ($snapshotId === null) {
            return ToolResult::error('snapshot_id is required for the "delete_snapshot" action.');
        }

        return $this->client->delete("virtual-machines/{$vmId}/snapshots/{$snapshotId}")
            ->toToolResultWith("Snapshot {$snapshotId} deleted for VM {$vmId}.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function requireInt(array $args, string $key): ?int
    {
        $value = $args[$key] ?? null;
        if ($value === null || $value === '' || $value === 0) {
            return null;
        }

        return (int) $value;
    }
}
