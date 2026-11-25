<x-filament-widgets::widget>
    <x-filament::section heading="Start here" description="The jobs people do most. Everything else is in the menu on the left.">
        @php($actions = $this->getActions())
        @if(count($actions) === 0)
            <p class="text-sm text-gray-500">Use the menu on the left to open the pages you have access to.</p>
        @else
            <div class="abu-tiles">
                @foreach($actions as $action)
                    <a href="{{ $action['url'] }}" class="abu-tile">
                        <span class="abu-tile__icon"><x-filament::icon :icon="$action['icon']" style="width:1.4rem;height:1.4rem" /></span>
                        <span class="abu-tile__text"><strong>{{ $action['label'] }}</strong><span>{{ $action['hint'] }}</span></span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
