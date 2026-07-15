@props([
    'permission',
    'label' => null,
])

@php
    $display = $label ?? (\App\Support\Agents\AgentPermission::labels()[$permission] ?? str_replace('agent.', '', $permission));
@endphp

<span class="ota-mobile-agent__perm-chip">{{ $display }}</span>
