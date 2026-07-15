{{-- MA-1: theme layout shell — delegates to the production mobile layout without visual changes.
     Mirrors themes/agent/default-agent/layouts/agent-portal.blade.php (MC-8D pattern).
     While config('ota-mobile.app_theme') is 'default-mobile', every mobile page renders exactly
     as it does today. --}}
@extends('layouts.mobile-app')
