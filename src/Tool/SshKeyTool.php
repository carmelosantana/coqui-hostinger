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
 * Manage SSH public keys for Hostinger VPS.
 *
 * Supports listing, creating, deleting keys, and attaching/detaching
 * keys to virtual machines.
 */
final readonly class SshKeyTool
{
    public function __construct(
        private HostingerClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'hostinger_ssh',
            description: 'Manage SSH public keys for Hostinger VPS — list, create, delete keys, and attach/detach keys to virtual machines.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: ['list', 'create', 'delete', 'attach', 'detach'],
                    required: true,
                ),
                new NumberParameter(
                    'key_id',
                    'Public key ID (required for delete, attach, detach).',
                    required: false,
                    integer: true,
                ),
                new NumberParameter(
                    'vm_id',
                    'Virtual machine ID (required for attach, detach).',
                    required: false,
                    integer: true,
                ),
                new StringParameter(
                    'name',
                    'Name for the SSH key (required for "create").',
                    required: false,
                ),
                new StringParameter(
                    'public_key',
                    'The SSH public key content, e.g. "ssh-rsa AAAA..." (required for "create").',
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
            'list' => $this->listKeys(),
            'create' => $this->createKey($args),
            'delete' => $this->deleteKey($args),
            'attach' => $this->attachKey($args),
            'detach' => $this->detachKey($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    private function listKeys(): ToolResult
    {
        return $this->client->paginate('public-keys')
            ->toToolResultWith('SSH public keys:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function createKey(array $args): ToolResult
    {
        $name = trim((string) ($args['name'] ?? ''));
        if ($name === '') {
            return ToolResult::error('name is required for the "create" action.');
        }

        $publicKey = trim((string) ($args['public_key'] ?? ''));
        if ($publicKey === '') {
            return ToolResult::error('public_key is required for the "create" action.');
        }

        return $this->client->post('public-keys', [
            'name' => $name,
            'key' => $publicKey,
        ])->toToolResultWith("SSH key '{$name}' created.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function deleteKey(array $args): ToolResult
    {
        $keyId = $this->requireInt($args, 'key_id');
        if ($keyId === null) {
            return ToolResult::error('key_id is required for the "delete" action.');
        }

        return $this->client->delete("public-keys/{$keyId}")
            ->toToolResultWith("SSH key {$keyId} deleted. Note: this does not remove the key from VMs it was attached to.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function attachKey(array $args): ToolResult
    {
        $keyId = $this->requireInt($args, 'key_id');
        if ($keyId === null) {
            return ToolResult::error('key_id is required for the "attach" action.');
        }

        $vmId = $this->requireInt($args, 'vm_id');
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "attach" action.');
        }

        return $this->client->post("public-keys/{$keyId}/attach/{$vmId}")
            ->toToolResultWith("SSH key {$keyId} attached to VM {$vmId}.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function detachKey(array $args): ToolResult
    {
        $keyId = $this->requireInt($args, 'key_id');
        if ($keyId === null) {
            return ToolResult::error('key_id is required for the "detach" action.');
        }

        $vmId = $this->requireInt($args, 'vm_id');
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "detach" action.');
        }

        return $this->client->post("public-keys/{$keyId}/detach/{$vmId}")
            ->toToolResultWith("SSH key {$keyId} detached from VM {$vmId}.");
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
