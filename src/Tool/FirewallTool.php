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
 * Manage Hostinger VPS firewalls and firewall rules.
 *
 * Supports listing, creating, deleting firewalls, activating/deactivating
 * firewalls on VMs, syncing rules, and full CRUD on firewall rules.
 */
final readonly class FirewallTool
{
    public function __construct(
        private HostingerClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'hostinger_firewall',
            description: 'Manage Hostinger VPS firewalls — list, create, delete firewalls, activate/deactivate on VMs, sync rules, and manage individual firewall rules (list, create, update, delete).',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: [
                        'list', 'get', 'create', 'delete',
                        'activate', 'deactivate', 'sync',
                        'list_rules', 'create_rule', 'update_rule', 'delete_rule',
                    ],
                    required: true,
                ),
                new NumberParameter(
                    'firewall_id',
                    'Firewall ID (required for most operations).',
                    required: false,
                    integer: true,
                ),
                new NumberParameter(
                    'vm_id',
                    'Virtual machine ID (required for activate, deactivate, sync).',
                    required: false,
                    integer: true,
                ),
                new NumberParameter(
                    'rule_id',
                    'Firewall rule ID (required for update_rule, delete_rule).',
                    required: false,
                    integer: true,
                ),
                new StringParameter(
                    'name',
                    'Firewall name (required for "create").',
                    required: false,
                ),
                new EnumParameter(
                    'protocol',
                    'Protocol for firewall rule (TCP, UDP, ICMP, GRE, ESP, AH).',
                    values: ['TCP', 'UDP', 'ICMP', 'GRE', 'ESP', 'AH'],
                    required: false,
                ),
                new StringParameter(
                    'port',
                    'Port or port range for firewall rule (e.g. "80", "8000:9000"). Not used for ICMP.',
                    required: false,
                ),
                new StringParameter(
                    'source',
                    'Source IP or CIDR for firewall rule (e.g. "0.0.0.0/0" for any, or specific IP).',
                    required: false,
                ),
                new EnumParameter(
                    'source_type',
                    'Source type for the rule.',
                    values: ['ip', 'anywhere'],
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
            'list' => $this->listFirewalls(),
            'get' => $this->getFirewall($args),
            'create' => $this->createFirewall($args),
            'delete' => $this->deleteFirewall($args),
            'activate' => $this->activateFirewall($args),
            'deactivate' => $this->deactivateFirewall($args),
            'sync' => $this->syncFirewall($args),
            'list_rules' => $this->listRules($args),
            'create_rule' => $this->createRule($args),
            'update_rule' => $this->updateRule($args),
            'delete_rule' => $this->deleteRule($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    private function listFirewalls(): ToolResult
    {
        return $this->client->paginate('firewall')
            ->toToolResultWith('Firewalls:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function getFirewall(array $args): ToolResult
    {
        $firewallId = $this->requireInt($args, 'firewall_id');
        if ($firewallId === null) {
            return ToolResult::error('firewall_id is required for the "get" action.');
        }

        return $this->client->get("firewall/{$firewallId}")
            ->toToolResultWith('Firewall details:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function createFirewall(array $args): ToolResult
    {
        $name = trim((string) ($args['name'] ?? ''));
        if ($name === '') {
            return ToolResult::error('name is required for the "create" action.');
        }

        return $this->client->post('firewall', ['name' => $name])
            ->toToolResultWith("Firewall '{$name}' created.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function deleteFirewall(array $args): ToolResult
    {
        $firewallId = $this->requireInt($args, 'firewall_id');
        if ($firewallId === null) {
            return ToolResult::error('firewall_id is required for the "delete" action.');
        }

        return $this->client->delete("firewall/{$firewallId}")
            ->toToolResultWith("Firewall {$firewallId} deleted.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function activateFirewall(array $args): ToolResult
    {
        $firewallId = $this->requireInt($args, 'firewall_id');
        if ($firewallId === null) {
            return ToolResult::error('firewall_id is required for the "activate" action.');
        }

        $vmId = $this->requireInt($args, 'vm_id');
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "activate" action.');
        }

        return $this->client->post("firewall/{$firewallId}/activate/{$vmId}")
            ->toToolResultWith("Firewall {$firewallId} activated on VM {$vmId}.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function deactivateFirewall(array $args): ToolResult
    {
        $firewallId = $this->requireInt($args, 'firewall_id');
        if ($firewallId === null) {
            return ToolResult::error('firewall_id is required for the "deactivate" action.');
        }

        $vmId = $this->requireInt($args, 'vm_id');
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "deactivate" action.');
        }

        return $this->client->post("firewall/{$firewallId}/deactivate/{$vmId}")
            ->toToolResultWith("Firewall {$firewallId} deactivated on VM {$vmId}.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function syncFirewall(array $args): ToolResult
    {
        $firewallId = $this->requireInt($args, 'firewall_id');
        if ($firewallId === null) {
            return ToolResult::error('firewall_id is required for the "sync" action.');
        }

        $vmId = $this->requireInt($args, 'vm_id');
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "sync" action.');
        }

        return $this->client->post("firewall/{$firewallId}/sync/{$vmId}")
            ->toToolResultWith("Firewall {$firewallId} rules synced to VM {$vmId}.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function listRules(array $args): ToolResult
    {
        $firewallId = $this->requireInt($args, 'firewall_id');
        if ($firewallId === null) {
            return ToolResult::error('firewall_id is required for the "list_rules" action.');
        }

        return $this->client->get("firewall/{$firewallId}/rules")
            ->toToolResultWith("Firewall {$firewallId} rules:");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function createRule(array $args): ToolResult
    {
        $firewallId = $this->requireInt($args, 'firewall_id');
        if ($firewallId === null) {
            return ToolResult::error('firewall_id is required for the "create_rule" action.');
        }

        $protocol = trim((string) ($args['protocol'] ?? ''));
        if ($protocol === '') {
            return ToolResult::error('protocol is required for the "create_rule" action.');
        }

        $body = ['protocol' => $protocol];

        $port = trim((string) ($args['port'] ?? ''));
        if ($port !== '') {
            $body['port'] = $port;
        }

        $source = trim((string) ($args['source'] ?? ''));
        if ($source !== '') {
            $body['source'] = $source;
        }

        $sourceType = trim((string) ($args['source_type'] ?? ''));
        if ($sourceType !== '') {
            $body['source_type'] = $sourceType;
        }

        return $this->client->post("firewall/{$firewallId}/rules", $body)
            ->toToolResultWith("Rule created on firewall {$firewallId}.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function updateRule(array $args): ToolResult
    {
        $firewallId = $this->requireInt($args, 'firewall_id');
        if ($firewallId === null) {
            return ToolResult::error('firewall_id is required for the "update_rule" action.');
        }

        $ruleId = $this->requireInt($args, 'rule_id');
        if ($ruleId === null) {
            return ToolResult::error('rule_id is required for the "update_rule" action.');
        }

        $body = [];

        $protocol = trim((string) ($args['protocol'] ?? ''));
        if ($protocol !== '') {
            $body['protocol'] = $protocol;
        }

        $port = trim((string) ($args['port'] ?? ''));
        if ($port !== '') {
            $body['port'] = $port;
        }

        $source = trim((string) ($args['source'] ?? ''));
        if ($source !== '') {
            $body['source'] = $source;
        }

        $sourceType = trim((string) ($args['source_type'] ?? ''));
        if ($sourceType !== '') {
            $body['source_type'] = $sourceType;
        }

        return $this->client->put("firewall/{$firewallId}/rules/{$ruleId}", $body)
            ->toToolResultWith("Rule {$ruleId} updated on firewall {$firewallId}. Note: VMs using this firewall need to be synced.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function deleteRule(array $args): ToolResult
    {
        $firewallId = $this->requireInt($args, 'firewall_id');
        if ($firewallId === null) {
            return ToolResult::error('firewall_id is required for the "delete_rule" action.');
        }

        $ruleId = $this->requireInt($args, 'rule_id');
        if ($ruleId === null) {
            return ToolResult::error('rule_id is required for the "delete_rule" action.');
        }

        return $this->client->delete("firewall/{$firewallId}/rules/{$ruleId}")
            ->toToolResultWith("Rule {$ruleId} deleted from firewall {$firewallId}. Note: VMs using this firewall will lose sync.");
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
