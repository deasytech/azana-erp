@props(['steps'])

{{-- A record's journey as numbered steps. Each step: label, state (done|current|stopped|todo) and an optional note. Display only. --}}
<ol {{ $attributes->class('flex flex-col gap-3 sm:flex-row sm:gap-0') }} aria-label="Progress">
    @foreach ($steps as $i => $step)
        @php
            $state = $step['state'];
            $dot = match ($state) {
                'done' => 'bg-success-600 text-white',
                'current' => 'bg-primary-500 text-white ring-4 ring-primary-500/20',
                'stopped' => 'bg-danger-600 text-white',
                default => 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400',
            };
        @endphp
        <li class="flex flex-1 items-center gap-3 sm:flex-col sm:gap-2 sm:text-center" @if ($state === 'current') aria-current="step" @endif>
            <div class="flex items-center sm:w-full">
                <span class="hidden h-px flex-1 sm:block {{ $i === 0 ? 'bg-transparent' : ($state === 'todo' ? 'bg-gray-200 dark:bg-white/10' : 'bg-success-600/50') }}"></span>
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums {{ $dot }}">
                    {{ $state === 'done' ? '✓' : ($state === 'stopped' ? '✕' : $i + 1) }}
                </span>
                <span class="hidden h-px flex-1 sm:block {{ $i === count($steps) - 1 ? 'bg-transparent' : ($state === 'done' ? 'bg-success-600/50' : 'bg-gray-200 dark:bg-white/10') }}"></span>
            </div>
            <div>
                <div class="text-sm font-medium {{ $state === 'todo' ? 'text-gray-500 dark:text-gray-400' : 'text-gray-950 dark:text-white' }}">{{ $step['label'] }}</div>
                @if (! empty($step['note']))
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $step['note'] }}</div>
                @endif
            </div>
        </li>
    @endforeach
</ol>
