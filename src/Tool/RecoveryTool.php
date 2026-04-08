<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitHostinger\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\CoquiToolkitHostinger\Runtime\HostingerClient;

/**
 * Manage VPS recovery mode.
 *
 * Start or stop recovery mode for a virtual machine.
 * Recovery mode mounts the original disk in /mnt for rescue operations.
 */
final readonly class RecoveryTool
{
    public function __construct(
        private HostingerClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'hostinger_recovery',
            description: 'Manage VPS recovery mode — start recovery (mounts original disk at /mnt for rescue) or stop recovery to return to normal operation.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: ['start', 'stop'],
                    required: true,
                ),
                new NumberParameter(
                    'vm_id',
                    'Virtual machine ID (required).',
                    required: true,
                    integer: true,
                ),
                new StringParameter(
                    'root_password',
                    'Root password for recovery mode (required for "start").',
                    required: false,
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
            'start' => $this->startRecovery($args),
            'stop' => $this->stopRecovery($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    /**
     * @param array<string, mixed> $args
     */
    private function startRecovery(array $args): ToolResult
    {
        $vmId = (int) ($args['vm_id'] ?? 0);
        if ($vmId === 0) {
            return ToolResult::error('vm_id is required for the "start" action.');
        }

        $body = [];
        $rootPassword = trim((string) ($args['root_password'] ?? ''));
        if ($rootPassword !== '') {
            $body['root_password'] = $rootPassword;
        }

        return $this->client->post("virtual-machines/{$vmId}/recovery", $body)
            ->toToolResultWith("Recovery mode started for VM {$vmId}. The original disk is mounted at /mnt.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function stopRecovery(array $args): ToolResult
    {
        $vmId = (int) ($args['vm_id'] ?? 0);
        if ($vmId === 0) {
            return ToolResult::error('vm_id is required for the "stop" action.');
        }

        return $this->client->delete("virtual-machines/{$vmId}/recovery")
            ->toToolResultWith("Recovery mode stopped for VM {$vmId}. Normal operation resumed.");
    }
}
