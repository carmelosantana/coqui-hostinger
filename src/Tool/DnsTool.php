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
 * Manage PTR (reverse DNS) records for VPS IP addresses.
 */
final readonly class DnsTool
{
    public function __construct(
        private HostingerClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'hostinger_dns',
            description: 'Manage PTR (reverse DNS) records for Hostinger VPS IP addresses — create/update or delete PTR records.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: ['create_ptr', 'delete_ptr'],
                    required: true,
                ),
                new NumberParameter(
                    'vm_id',
                    'Virtual machine ID (required).',
                    required: true,
                    integer: true,
                ),
                new NumberParameter(
                    'ip_address_id',
                    'IP address ID (required). Get this from the VM details.',
                    required: true,
                    integer: true,
                ),
                new StringParameter(
                    'ptr_record',
                    'PTR record value, e.g. "mail.example.com" (required for "create_ptr").',
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
            'create_ptr' => $this->createPtr($args),
            'delete_ptr' => $this->deletePtr($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    /**
     * @param array<string, mixed> $args
     */
    private function createPtr(array $args): ToolResult
    {
        $vmId = (int) ($args['vm_id'] ?? 0);
        if ($vmId === 0) {
            return ToolResult::error('vm_id is required for the "create_ptr" action.');
        }

        $ipId = (int) ($args['ip_address_id'] ?? 0);
        if ($ipId === 0) {
            return ToolResult::error('ip_address_id is required for the "create_ptr" action.');
        }

        $ptrRecord = trim((string) ($args['ptr_record'] ?? ''));
        if ($ptrRecord === '') {
            return ToolResult::error('ptr_record is required for the "create_ptr" action.');
        }

        return $this->client->post("virtual-machines/{$vmId}/ptr/{$ipId}", [
            'ptr_record' => $ptrRecord,
        ])->toToolResultWith("PTR record set to '{$ptrRecord}' for IP {$ipId} on VM {$vmId}.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function deletePtr(array $args): ToolResult
    {
        $vmId = (int) ($args['vm_id'] ?? 0);
        if ($vmId === 0) {
            return ToolResult::error('vm_id is required for the "delete_ptr" action.');
        }

        $ipId = (int) ($args['ip_address_id'] ?? 0);
        if ($ipId === 0) {
            return ToolResult::error('ip_address_id is required for the "delete_ptr" action.');
        }

        return $this->client->delete("virtual-machines/{$vmId}/ptr/{$ipId}")
            ->toToolResultWith("PTR record deleted for IP {$ipId} on VM {$vmId}.");
    }
}
