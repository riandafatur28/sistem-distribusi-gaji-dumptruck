<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @switch($type ?? 'text')
        @case('card')
            <div class="skel-card" style="{{ $style ?? '' }}"></div>
            @break
        @case('table')
            <div class="space-y-2">
                @for($i = 0; $i < ($rows ?? 5); $i++)
                    <div class="skel-row" style="width: {{ rand(70, 100) }}%;"></div>
                @endfor
            </div>
            @break
        @case('stat')
            <div class="flex gap-4">
                @for($i = 0; $i < ($count ?? 4); $i++)
                    <div class="skel-card flex-1" style="height: 90px;"></div>
                @endfor
            </div>
            @break
        @case('chart')
            <div class="skel-card" style="height: {{ $height ?? '250px' }};"></div>
            @break
        @default
            <div class="space-y-2">
                @for($i = 0; $i < ($lines ?? 4); $i++)
                    <div class="skel-text" style="width: {{ rand(60, 95) }}%;"></div>
                @endfor
            </div>
    @endswitch
</div>
