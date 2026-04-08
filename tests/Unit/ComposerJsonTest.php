<?php

declare(strict_types=1);

test('composer.json declares HOSTINGER_API_TOKEN credential', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['php-agents']['credentials'])->toHaveKey('HOSTINGER_API_TOKEN');
});

test('composer.json declares gated tools', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    $gated = $composer['extra']['php-agents']['gated'];

    expect($gated)->toHaveKeys([
        'hostinger_vps',
        'hostinger_firewall',
        'hostinger_ssh',
        'hostinger_backup',
        'hostinger_recovery',
        'hostinger_script',
    ]);
});

test('composer.json declares toolkit class', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['php-agents']['toolkits'])
        ->toContain('CoquiBot\\Toolkits\\Hostinger\\HostingerToolkit');
});

test('gated VPS actions include destructive operations', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    $vpsGated = $composer['extra']['php-agents']['gated']['hostinger_vps'];

    expect($vpsGated)->toContain('recreate');
    expect($vpsGated)->toContain('setup');
    expect($vpsGated)->toContain('set_password');
});

test('gated firewall actions include destructive operations', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    $firewallGated = $composer['extra']['php-agents']['gated']['hostinger_firewall'];

    expect($firewallGated)->toContain('delete');
    expect($firewallGated)->toContain('deactivate');
    expect($firewallGated)->toContain('delete_rule');
});

test('gated backup actions include restore operations', function () {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    $backupGated = $composer['extra']['php-agents']['gated']['hostinger_backup'];

    expect($backupGated)->toContain('restore_backup');
    expect($backupGated)->toContain('restore_snapshot');
    expect($backupGated)->toContain('delete_snapshot');
});
