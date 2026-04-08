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
 * Manage Hostinger VPS virtual machines.
 *
 * Supports listing, inspecting, starting, stopping, restarting,
 * recreating, setting up, changing hostname, and resetting root password.
 */
final readonly class VpsTool
{
    public function __construct(
        private HostingerClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'hostinger_vps',
            description: 'Manage Hostinger VPS virtual machines — list, inspect, start, stop, restart, recreate, setup, set hostname, or reset root password.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: ['list', 'get', 'start', 'stop', 'restart', 'recreate', 'setup', 'set_hostname', 'set_password'],
                    required: true,
                ),
                new NumberParameter(
                    'vm_id',
                    'Virtual machine ID (required for all actions except "list").',
                    required: false,
                    integer: true,
                ),
                new StringParameter(
                    'hostname',
                    'New hostname (required for "set_hostname" and "setup").',
                    required: false,
                ),
                new StringParameter(
                    'password',
                    'New root password (required for "set_password", "setup", and "recreate").',
                    required: false,
                ),
                new NumberParameter(
                    'template_id',
                    'OS template ID (required for "setup" and "recreate").',
                    required: false,
                    integer: true,
                ),
                new NumberParameter(
                    'post_install_script_id',
                    'Post-install script ID (optional for "setup" and "recreate").',
                    required: false,
                    integer: true,
                ),
                new StringParameter(
                    'ns1',
                    'Primary nameserver (optional for "set_hostname").',
                    required: false,
                ),
                new StringParameter(
                    'ns2',
                    'Secondary nameserver (optional for "set_hostname").',
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
            'list' => $this->listVms(),
            'get' => $this->getVm($args),
            'start' => $this->startVm($args),
            'stop' => $this->stopVm($args),
            'restart' => $this->restartVm($args),
            'recreate' => $this->recreateVm($args),
            'setup' => $this->setupVm($args),
            'set_hostname' => $this->setHostname($args),
            'set_password' => $this->setPassword($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    private function listVms(): ToolResult
    {
        return $this->client->get('virtual-machines')
            ->toToolResultWith('Virtual machines:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function getVm(array $args): ToolResult
    {
        $vmId = $this->requireVmId($args);
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "get" action.');
        }

        return $this->client->get("virtual-machines/{$vmId}")
            ->toToolResultWith('Virtual machine details:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function startVm(array $args): ToolResult
    {
        $vmId = $this->requireVmId($args);
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "start" action.');
        }

        return $this->client->post("virtual-machines/{$vmId}/start")
            ->toToolResultWith("Virtual machine {$vmId} start initiated.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function stopVm(array $args): ToolResult
    {
        $vmId = $this->requireVmId($args);
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "stop" action.');
        }

        return $this->client->post("virtual-machines/{$vmId}/stop")
            ->toToolResultWith("Virtual machine {$vmId} stop initiated.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function restartVm(array $args): ToolResult
    {
        $vmId = $this->requireVmId($args);
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "restart" action.');
        }

        return $this->client->post("virtual-machines/{$vmId}/restart")
            ->toToolResultWith("Virtual machine {$vmId} restart initiated.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function recreateVm(array $args): ToolResult
    {
        $vmId = $this->requireVmId($args);
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "recreate" action.');
        }

        $password = trim((string) ($args['password'] ?? ''));
        if ($password === '') {
            return ToolResult::error('password is required for the "recreate" action.');
        }

        $body = ['password' => $password];

        $templateId = $args['template_id'] ?? null;
        if ($templateId !== null) {
            $body['template_id'] = (int) $templateId;
        }

        $scriptId = $args['post_install_script_id'] ?? null;
        if ($scriptId !== null) {
            $body['post_install_script_id'] = (int) $scriptId;
        }

        return $this->client->post("virtual-machines/{$vmId}/recreate", $body)
            ->toToolResultWith("Virtual machine {$vmId} recreate initiated. WARNING: This destroys all existing data.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function setupVm(array $args): ToolResult
    {
        $vmId = $this->requireVmId($args);
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "setup" action.');
        }

        $templateId = $args['template_id'] ?? null;
        if ($templateId === null) {
            return ToolResult::error('template_id is required for the "setup" action.');
        }

        $password = trim((string) ($args['password'] ?? ''));
        if ($password === '') {
            return ToolResult::error('password is required for the "setup" action.');
        }

        $hostname = trim((string) ($args['hostname'] ?? ''));
        if ($hostname === '') {
            return ToolResult::error('hostname is required for the "setup" action.');
        }

        $body = [
            'template_id' => (int) $templateId,
            'password' => $password,
            'hostname' => $hostname,
        ];

        $scriptId = $args['post_install_script_id'] ?? null;
        if ($scriptId !== null) {
            $body['post_install_script_id'] = (int) $scriptId;
        }

        return $this->client->post("virtual-machines/{$vmId}/setup", $body)
            ->toToolResultWith("Virtual machine {$vmId} setup initiated.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function setHostname(array $args): ToolResult
    {
        $vmId = $this->requireVmId($args);
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "set_hostname" action.');
        }

        $hostname = trim((string) ($args['hostname'] ?? ''));
        if ($hostname === '') {
            return ToolResult::error('hostname is required for the "set_hostname" action.');
        }

        $body = ['hostname' => $hostname];

        $password = trim((string) ($args['password'] ?? ''));
        if ($password !== '') {
            $body['password'] = $password;
        }

        $ns1 = trim((string) ($args['ns1'] ?? ''));
        if ($ns1 !== '') {
            $body['ns1'] = $ns1;
        }

        $ns2 = trim((string) ($args['ns2'] ?? ''));
        if ($ns2 !== '') {
            $body['ns2'] = $ns2;
        }

        return $this->client->put("virtual-machines/{$vmId}/hostname", $body)
            ->toToolResultWith("Hostname for VM {$vmId} updated to '{$hostname}'.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function setPassword(array $args): ToolResult
    {
        $vmId = $this->requireVmId($args);
        if ($vmId === null) {
            return ToolResult::error('vm_id is required for the "set_password" action.');
        }

        $password = trim((string) ($args['password'] ?? ''));
        if ($password === '') {
            return ToolResult::error('password is required for the "set_password" action.');
        }

        return $this->client->put("virtual-machines/{$vmId}/root-password", ['password' => $password])
            ->toToolResultWith("Root password for VM {$vmId} updated.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function requireVmId(array $args): ?int
    {
        $vmId = $args['vm_id'] ?? null;
        if ($vmId === null || $vmId === '' || $vmId === 0) {
            return null;
        }

        return (int) $vmId;
    }
}
