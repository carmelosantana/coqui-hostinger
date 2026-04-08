<?php

declare(strict_types=1);

use CarmeloSantana\PHPAgents\Enum\ToolResultStatus;
use CarmeloSantana\CoquiToolkitHostinger\HostingerToolkit;
use CarmeloSantana\CoquiToolkitHostinger\Runtime\HostingerClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// -- Helpers ------------------------------------------------------------------

function mockHostingerToolkit(MockHttpClient $mockHttp): HostingerToolkit
{
    $client = new HostingerClient(apiToken: 'test-token', httpClient: $mockHttp);
    return new HostingerToolkit($client);
}

function findTool(HostingerToolkit $toolkit, string $name): \CarmeloSantana\PHPAgents\Contract\ToolInterface
{
    foreach ($toolkit->tools() as $tool) {
        if ($tool->name() === $name) {
            return $tool;
        }
    }
    throw new \RuntimeException("Tool '{$name}' not found");
}

// -- VPS Tool -----------------------------------------------------------------

test('hostinger_vps list returns VM data', function () {
    $mockClient = new MockHttpClient([
        new MockResponse(json_encode([
            ['id' => 1, 'hostname' => 'web-01', 'state' => 'running'],
            ['id' => 2, 'hostname' => 'db-01', 'state' => 'stopped'],
        ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]),
    ]);

    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_vps');
    $result = $tool->execute(['action' => 'list']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($result->content)->toContain('web-01');
    expect($result->content)->toContain('db-01');
});

test('hostinger_vps get requires vm_id', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_vps');
    $result = $tool->execute(['action' => 'get']);

    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('vm_id');
});

test('hostinger_vps start sends correct request', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url];
        return new MockResponse(json_encode(['id' => 100, 'name' => 'start']), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_vps');
    $result = $tool->execute(['action' => 'start', 'vm_id' => 42]);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($captured['url'])->toContain('virtual-machines/42/start');
    expect($captured['method'])->toBe('POST');
});

test('hostinger_vps set_hostname validates required fields', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_vps');

    $result = $tool->execute(['action' => 'set_hostname', 'vm_id' => 1]);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('hostname');
});

test('hostinger_vps recreate validates password', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_vps');

    $result = $tool->execute(['action' => 'recreate', 'vm_id' => 1]);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('password');
});

test('hostinger_vps setup validates all required fields', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_vps');

    // Missing template_id
    $result = $tool->execute(['action' => 'setup', 'vm_id' => 1, 'password' => 'pass', 'hostname' => 'test']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('template_id');
});

test('hostinger_vps unknown action returns error', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_vps');

    $result = $tool->execute(['action' => 'nonexistent']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('Unknown action');
});

// -- Firewall Tool ------------------------------------------------------------

test('hostinger_firewall list returns firewalls', function () {
    $page = 0;
    $mockClient = new MockHttpClient(function () use (&$page): MockResponse {
        $page++;
        if ($page === 1) {
            return new MockResponse(json_encode([
                ['id' => 10, 'name' => 'web-fw'],
            ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
        }
        return new MockResponse(json_encode([]), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_firewall');
    $result = $tool->execute(['action' => 'list']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($result->content)->toContain('web-fw');
});

test('hostinger_firewall create requires name', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_firewall');

    $result = $tool->execute(['action' => 'create']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('name');
});

test('hostinger_firewall activate requires both firewall_id and vm_id', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_firewall');

    $result = $tool->execute(['action' => 'activate', 'firewall_id' => 10]);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('vm_id');
});

test('hostinger_firewall create_rule validates protocol', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_firewall');

    $result = $tool->execute(['action' => 'create_rule', 'firewall_id' => 10]);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('protocol');
});

// -- SSH Key Tool -------------------------------------------------------------

test('hostinger_ssh create validates required fields', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_ssh');

    $result = $tool->execute(['action' => 'create', 'name' => 'my-key']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('public_key');
});

test('hostinger_ssh attach requires both key_id and vm_id', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_ssh');

    $result = $tool->execute(['action' => 'attach', 'key_id' => 5]);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('vm_id');
});

// -- Backup Tool --------------------------------------------------------------

test('hostinger_backup list_backups requires vm_id', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_backup');

    $result = $tool->execute(['action' => 'list_backups']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('vm_id');
});

test('hostinger_backup restore_backup requires backup_id', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_backup');

    $result = $tool->execute(['action' => 'restore_backup', 'vm_id' => 1]);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('backup_id');
});

test('hostinger_backup create_snapshot sends correct request', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url];
        return new MockResponse(json_encode(['id' => 200, 'name' => 'snapshot']), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_backup');
    $result = $tool->execute(['action' => 'create_snapshot', 'vm_id' => 5]);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($captured['url'])->toContain('virtual-machines/5/snapshots');
    expect($captured['method'])->toBe('POST');
});

// -- Recovery Tool ------------------------------------------------------------

test('hostinger_recovery start sends correct request', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url];
        return new MockResponse(json_encode(['id' => 300, 'name' => 'recovery']), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_recovery');
    $result = $tool->execute(['action' => 'start', 'vm_id' => 7, 'root_password' => 'rescue123']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($captured['url'])->toContain('virtual-machines/7/recovery');
    expect($captured['method'])->toBe('POST');
});

test('hostinger_recovery stop uses DELETE method', function () {
    $captured = [];
    $mockClient = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
        $captured = ['method' => $method, 'url' => $url];
        return new MockResponse('', [
            'http_code' => 204,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    });

    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_recovery');
    $result = $tool->execute(['action' => 'stop', 'vm_id' => 7]);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($captured['method'])->toBe('DELETE');
});

// -- DNS Tool -----------------------------------------------------------------

test('hostinger_dns create_ptr validates ptr_record', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_dns');

    $result = $tool->execute(['action' => 'create_ptr', 'vm_id' => 1, 'ip_address_id' => 100]);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('ptr_record');
});

// -- Template Tool ------------------------------------------------------------

test('hostinger_template list_templates returns data', function () {
    $mockClient = new MockHttpClient([
        new MockResponse(json_encode([
            ['id' => 1, 'name' => 'Ubuntu 22.04'],
            ['id' => 2, 'name' => 'Debian 12'],
        ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]),
    ]);

    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_template');
    $result = $tool->execute(['action' => 'list_templates']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($result->content)->toContain('Ubuntu 22.04');
});

test('hostinger_template list_data_centers returns data', function () {
    $mockClient = new MockHttpClient([
        new MockResponse(json_encode([
            ['id' => 1, 'name' => 'US East', 'location' => 'New York'],
        ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]),
    ]);

    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_template');
    $result = $tool->execute(['action' => 'list_data_centers']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($result->content)->toContain('US East');
});

// -- Script Tool --------------------------------------------------------------

test('hostinger_script create validates name and script', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_script');

    // Missing script
    $result = $tool->execute(['action' => 'create', 'name' => 'test-script']);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('script');
});

test('hostinger_script create rejects oversized scripts', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_script');

    $result = $tool->execute([
        'action' => 'create',
        'name' => 'huge-script',
        'script' => str_repeat('x', 50000), // > 48KB
    ]);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('48KB');
});

test('hostinger_script update requires at least one field', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_script');

    $result = $tool->execute(['action' => 'update', 'script_id' => 1]);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('name or script');
});

// -- Action Tool --------------------------------------------------------------

test('hostinger_actions requires vm_id', function () {
    $mockClient = new MockHttpClient([]);
    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_actions');

    $result = $tool->execute([]);
    expect($result->status)->toBe(ToolResultStatus::Error);
    expect($result->content)->toContain('vm_id');
});

test('hostinger_actions returns action list', function () {
    $mockClient = new MockHttpClient([
        new MockResponse(json_encode([
            ['id' => 1, 'name' => 'start', 'status' => 'completed'],
            ['id' => 2, 'name' => 'restart', 'status' => 'completed'],
        ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]),
    ]);

    $toolkit = mockHostingerToolkit($mockClient);
    $tool = findTool($toolkit, 'hostinger_actions');
    $result = $tool->execute(['vm_id' => 42]);

    expect($result->status)->toBe(ToolResultStatus::Success);
    expect($result->content)->toContain('start');
    expect($result->content)->toContain('restart');
});
