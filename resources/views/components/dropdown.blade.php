@props([
    'align' => 'right',
    'width' => '48',
    'contentClasses' => 'py-1 bg-white dark:bg-gray-700',
    'dropdownClasses' => '',
])

@php
    switch ($align) {
        case 'left':
            $alignmentClasses = 'ltr:origin-top-left rtl:origin-top-right start-0';
            break;
        case 'top':
            $alignmentClasses = 'origin-top';
            break;
        case 'none':
        case 'false':
            $alignmentClasses = '';
            break;
        case 'right':
        default:
            $alignmentClasses = 'ltr:origin-top-right rtl:origin-top-left end-0';
            break;
    }

    switch ($width) {
        case '32':
            $width = 'w-32';
            break;
        case '36':
            $width = 'w-36';
            break;
        case '40':
            $width = 'w-40';
            break;
        case '44':
            $width = 'w-44';
            break;
        case '48':
            $width = 'w-48';
            break;
        case '52':
            $width = 'w-52';
            break;
        case '56':
            $width = 'w-56';
            break;
        case '64':
            $width = 'w-64';
            break;
    }
@endphp

<div class="relative" x-data="{ open: false }" @click.away="open = false" @close.stop="open = false">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute z-50 mt-2 {{ $width }} rounded-md shadow-lg {{ $alignmentClasses }} {{ $dropdownClasses }}"
        style="display: none;" @click="open = false">
        <div class="rounded-md ring-1 ring-black ring-opacity-5 z-50 {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
