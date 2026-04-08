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
 * Manage VPS post-install scripts.
 *
 * Post-install scripts are saved to /post_install and executed after
 * VM installation, with output redirected to /post_install.log.
 * Maximum script size is 48KB.
 */
final readonly class ScriptTool
{
    public function __construct(
        private HostingerClient $client,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'hostinger_script',
            description: 'Manage VPS post-install scripts — list, get, create, update, or delete scripts that run automatically after VM setup. Scripts are saved to /post_install (max 48KB) with output in /post_install.log.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Operation to perform.',
                    values: ['list', 'get', 'create', 'update', 'delete'],
                    required: true,
                ),
                new NumberParameter(
                    'script_id',
                    'Post-install script ID (required for get, update, delete).',
                    required: false,
                    integer: true,
                ),
                new StringParameter(
                    'name',
                    'Script name (required for create, optional for update).',
                    required: false,
                ),
                new StringParameter(
                    'script',
                    'Script content, e.g. "#!/bin/bash\napt update && apt install -y nginx" (required for create, optional for update). Max 48KB.',
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
            'list' => $this->listScripts(),
            'get' => $this->getScript($args),
            'create' => $this->createScript($args),
            'update' => $this->updateScript($args),
            'delete' => $this->deleteScript($args),
            default => ToolResult::error("Unknown action: {$action}"),
        };
    }

    private function listScripts(): ToolResult
    {
        return $this->client->paginate('post-install-scripts')
            ->toToolResultWith('Post-install scripts:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function getScript(array $args): ToolResult
    {
        $scriptId = $this->requireInt($args, 'script_id');
        if ($scriptId === null) {
            return ToolResult::error('script_id is required for the "get" action.');
        }

        return $this->client->get("post-install-scripts/{$scriptId}")
            ->toToolResultWith('Post-install script details:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function createScript(array $args): ToolResult
    {
        $name = trim((string) ($args['name'] ?? ''));
        if ($name === '') {
            return ToolResult::error('name is required for the "create" action.');
        }

        $script = (string) ($args['script'] ?? '');
        if ($script === '') {
            return ToolResult::error('script is required for the "create" action.');
        }

        if (strlen($script) > 49152) { // 48KB
            return ToolResult::error('Script exceeds the 48KB maximum size limit.');
        }

        return $this->client->post('post-install-scripts', [
            'name' => $name,
            'script' => $script,
        ])->toToolResultWith("Post-install script '{$name}' created.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function updateScript(array $args): ToolResult
    {
        $scriptId = $this->requireInt($args, 'script_id');
        if ($scriptId === null) {
            return ToolResult::error('script_id is required for the "update" action.');
        }

        $body = [];

        $name = trim((string) ($args['name'] ?? ''));
        if ($name !== '') {
            $body['name'] = $name;
        }

        $script = (string) ($args['script'] ?? '');
        if ($script !== '') {
            if (strlen($script) > 49152) { // 48KB
                return ToolResult::error('Script exceeds the 48KB maximum size limit.');
            }
            $body['script'] = $script;
        }

        if ($body === []) {
            return ToolResult::error('At least one of name or script is required for the "update" action.');
        }

        return $this->client->put("post-install-scripts/{$scriptId}", $body)
            ->toToolResultWith("Post-install script {$scriptId} updated.");
    }

    /**
     * @param array<string, mixed> $args
     */
    private function deleteScript(array $args): ToolResult
    {
        $scriptId = $this->requireInt($args, 'script_id');
        if ($scriptId === null) {
            return ToolResult::error('script_id is required for the "delete" action.');
        }

        return $this->client->delete("post-install-scripts/{$scriptId}")
            ->toToolResultWith("Post-install script {$scriptId} deleted.");
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
