@if ($user instanceof \App\Models\User)
    <span class="inline-flex items-center gap-2 align-middle">
        <x-filament-panels::avatar.user :user="$user" size="sm" />
        <span class="text-sm font-medium">{{ filament()->getUserName($user) }}</span>
    </span>
@endif
