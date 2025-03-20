@php
    $hasFailedStatus = collect($events)->contains(fn($event) => $event['status'] === 'Failed');
@endphp

<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <style>
        @keyframes customPulse {
            0% {
                opacity: 0;
            }
            50% {
                opacity: 1;
            }
            100% {
                opacity: 0;
            }
        }
    </style>

    <x-pulse::card-header name="Schedule">
        <x-slot:icon>
            <span>
                <svg 
                    xmlns="http://www.w3.org/2000/svg" 
                    class="w-6 h-6 text-gray-500" 
                    viewBox="0 0 24 24" 
                    fill="none" 
                    stroke="currentColor" 
                    stroke-width="2" 
                    stroke-linecap="round" 
                    stroke-linejoin="round"
                    style="{{ $hasFailedStatus ? 'animation: customPulse 1.5s infinite ease-in-out;' : '' }}">
                    <rect x="3" y="4" width="18" height="16" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                    <path d="M12 14h.01"></path>
                    <path d="M12 17h.01"></path>
                </svg>
            </span>
        </x-slot:icon>
        <x-slot:actions>
            <x-pulse::select 
                wire:model.live="selectedcountry" 
                label="Show" 
                :options="['ALL' => 'All', 'UGA' => 'UGA', 'RWA' => 'RWA', 'MDG' => 'MDG']" 
            />
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if($events->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Task</x-pulse::th>
                        <x-pulse::th>Next Due</x-pulse::th>
                        <x-pulse::th>Expression</x-pulse::th>
                        <x-pulse::th>Status</x-pulse::th>
                        <x-pulse::th>Failed At</x-pulse::th>
                        <x-pulse::th>Reason</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach($events as $event)
                        <tr class="h-2 first:h-0" wire:key="{{ $event['command'] }}-spacer"></tr>
                        <tr wire:key="{{ $event['command'] }}-row">
                            <x-pulse::td>{{ $event['command'] }}</x-pulse::td>
                            <x-pulse::td>{{ $event['next_due'] }}</x-pulse::td>
                            <x-pulse::td>{{ $event['expression'] }}</x-pulse::td>
                            <x-pulse::td>
                                <span 
                                    style="color: {{ $event['status'] === 'Failed' ? '#dc2626' : '#16a34a' }}; 
                                        font-weight: bold; 
                                        {{ $event['status'] === 'Failed' ? 'animation: pulse 1.5s infinite;' : '' }}">
                                    {{ $event['status'] }}
                                </span>
                            </x-pulse::td>
                            <x-pulse::td class="text-center">
                                {{ $event['failed_at'] ?? '-' }}
                            </x-pulse::td>
                            <x-pulse::td class="text-center">
                                {{ $event['reason'] ?? '-' }}
                            </x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
