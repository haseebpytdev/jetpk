@php
    $activeTab = $activeTab ?? 'general';
@endphp
@if ($profile->is_master_profile)
    <div class="alert alert-warning" role="alert">
        <strong>Master deployment profile.</strong>
        Changes affect the primary testing server. Confirm before saving on any tab.
    </div>
@endif

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'general' ? 'active' : '' }}"
           href="{{ route('dev.cp.clients.edit', $profile) }}">General</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'branding' ? 'active' : '' }}"
           href="{{ route('dev.cp.clients.branding', $profile) }}">Branding</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'modules' ? 'active' : '' }}"
           href="{{ route('dev.cp.clients.modules', $profile) }}">Modules</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'suppliers' ? 'active' : '' }}"
           href="{{ route('dev.cp.clients.suppliers', $profile) }}">Suppliers</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'theme' ? 'active' : '' }}"
           href="{{ route('dev.cp.clients.theme', $profile) }}">Theme</a>
    </li>
</ul>
