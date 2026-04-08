<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitHostinger\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\CoquiToolkitHostinger\Runtime\HostingerClient;

/**
 * List actions (audit log) performed on a virtual machine.
 */
final readonly class ActionTool
{
    public function __construct(
        private HostingerClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'hostinger_actions',
            description: 'List recent actions (audit log) performed on a Hostinger VPS virtual machine.',
            parameters: [
                new NumberParameter(
                    'vm_id',
                    'Virtual machine ID (required).',
                    required: true,
                    integer: true,
                ),
                new NumberParameter(
                    'page',
                    'Page number for pagination (default: 1).',
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
        $vmId = (int) ($args['vm_id'] ?? 0);
        if ($vmId === 0) {
            return ToolResult::error('vm_id is required.');
        }

        $query = [];
        $page = $args['page'] ?? null;
        if ($page !== null) {
            $query['page'] = (int) $page;
        }

        return $this->client->get("virtual-machines/{$vmId}/actions", $query)
            ->toToolResultWith("Actions for VM {$vmId}:");
    }
}
