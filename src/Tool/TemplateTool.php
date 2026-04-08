<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitHostinger\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\CoquiToolkitHostinger\Runtime\HostingerClient;

/**
 * List available OS templates and data centers for VPS provisioning.
 */
final readonly class TemplateTool
{
    public function __construct(
        private HostingerClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'hostinger_template',
            description: 'List available OS templates and data centers for Hostinger VPS provisioning.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: ['list_templates', 'list_data_centers'],
                    required: true,
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
            'list_templates' => $this->listTemplates(),
            'list_data_centers' => $this->listDataCenters(),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    private function listTemplates(): ToolResult
    {
        return $this->client->get('templates')
            ->toToolResultWith('Available OS templates:');
    }

    private function listDataCenters(): ToolResult
    {
        return $this->client->get('data-centers')
            ->toToolResultWith('Available data centers:');
    }
}
