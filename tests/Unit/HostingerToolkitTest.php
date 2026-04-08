<?php

declare(strict_types=1);

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;
use CarmeloSantana\CoquiToolkitHostinger\HostingerToolkit;

test('toolkit implements ToolkitInterface', function () {
    $toolkit = new HostingerToolkit();

    expect($toolkit)->toBeInstanceOf(ToolkitInterface::class);
});

test('tools returns all 9 tools', function () {
    $toolkit = new HostingerToolkit();

    expect($toolkit->tools())->toHaveCount(9);
});

test('each tool implements ToolInterface', function () {
    $toolkit = new HostingerToolkit();

    foreach ($toolkit->tools() as $tool) {
        expect($tool)->toBeInstanceOf(ToolInterface::class);
    }
});

test('tool names are unique', function () {
    $toolkit = new HostingerToolkit();
    $names = array_map(fn(ToolInterface $t) => $t->name(), $toolkit->tools());

    expect($names)->toHaveCount(count(array_unique($names)));
});

test('all tool names start with hostinger_', function () {
    $toolkit = new HostingerToolkit();

    foreach ($toolkit->tools() as $tool) {
        expect($tool->name())->toStartWith('hostinger_');
    }
});

test('expected tool names are registered', function () {
    $toolkit = new HostingerToolkit();
    $names = array_map(fn(ToolInterface $t) => $t->name(), $toolkit->tools());

    expect($names)->toContain('hostinger_vps');
    expect($names)->toContain('hostinger_firewall');
    expect($names)->toContain('hostinger_ssh');
    expect($names)->toContain('hostinger_backup');
    expect($names)->toContain('hostinger_recovery');
    expect($names)->toContain('hostinger_dns');
    expect($names)->toContain('hostinger_template');
    expect($names)->toContain('hostinger_script');
    expect($names)->toContain('hostinger_actions');
});

test('each tool produces a valid function schema', function () {
    $toolkit = new HostingerToolkit();

    foreach ($toolkit->tools() as $tool) {
        $schema = $tool->toFunctionSchema();

        expect($schema)
            ->toBeArray()
            ->toHaveKeys(['type', 'function']);

        expect($schema['type'])->toBe('function');
        expect($schema['function'])->toBeArray()->toHaveKeys(['name', 'description', 'parameters']);
        expect($schema['function']['name'])->toBeString()->not->toBeEmpty();
        expect($schema['function']['description'])->toBeString()->not->toBeEmpty();
        expect($schema['function']['parameters'])->toBeArray();
    }
});

test('guidelines contain XML tags', function () {
    $toolkit = new HostingerToolkit();
    $guidelines = $toolkit->guidelines();

    expect($guidelines)->toContain('<HOSTINGER-GUIDELINES>');
    expect($guidelines)->toContain('</HOSTINGER-GUIDELINES>');
});

test('guidelines mention all tool names', function () {
    $toolkit = new HostingerToolkit();
    $guidelines = $toolkit->guidelines();

    expect($guidelines)->toContain('hostinger_vps');
    expect($guidelines)->toContain('hostinger_firewall');
    expect($guidelines)->toContain('hostinger_ssh');
    expect($guidelines)->toContain('hostinger_backup');
    expect($guidelines)->toContain('hostinger_recovery');
    expect($guidelines)->toContain('hostinger_dns');
    expect($guidelines)->toContain('hostinger_template');
    expect($guidelines)->toContain('hostinger_script');
    expect($guidelines)->toContain('hostinger_actions');
});
