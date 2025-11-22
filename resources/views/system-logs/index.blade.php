@php
    $errorCount = $failedCount + $warningCount;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-[14px] bg-[linear-gradient(135deg,#155DFC_0%,#4F39F6_100%)] shadow-[0_10px_15px_-3px_rgba(0,0,0,0.10),0_4px_6px_-4px_rgba(0,0,0,0.10)] flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-5 text-white">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </div>
            <div class="flex flex-col gap-1">
                <p class="text-[16px] font-medium text-black">System Logs</p>
                <p class="text-sm font-medium text-gray-600">View system activities and events</p>
            </div>
        </div>
    </x-slot>

    <div class="flex flex-col gap-6 md:gap-8 lg:gap-10">
        <div
            class="w-full bg-gradient-to-r from-gray-700 to-gray-900 rounded-xl p-5 md:p-8 lg:p-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 lg:gap-4">
            <div class="flex flex-col gap-4 md:gap-5 w-full lg:w-auto">
                <div class="flex items-center gap-3 md:gap-4">
                    <div class="bg-gray-500 w-[40px] h-[40px] md:w-[50px] md:h-[50px] rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5 md:w-7 md:h-7 text-white">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <p class="text-white font-semibold text-lg md:text-xl lg:text-2xl">System Activity Log</p>
                        <p class="text-white text-sm md:text-base font-normal">Monitor all system activities and events</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-row items-center justify-between sm:justify-start gap-6 sm:gap-8 md:gap-10 w-full lg:w-auto">
                <div class="flex flex-col gap-1">
                    <p class="text-white font-medium text-xs md:text-sm">Total Events</p>
                    <p class="text-2xl md:text-3xl font-bold text-white self-end">{{ $totalEvents }}</p>
                </div>
                <div class="flex flex-col">
                    <p class="text-white font-medium text-xs md:text-sm">Success Rate</p>
                    <p class="text-2xl md:text-3xl font-bold text-white self-end">{{ $successRate }} %</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5 lg:gap-7">
            <div class="flex border border-gray-200 min-h-[120px] md:min-h-[150px] items-center justify-between shadow-md rounded-xl bg-white p-5 md:p-7 md:pt-10">
                <div class="flex flex-col gap-1">
                    <p class="text-gray-500 text-sm md:text-base">Total Events</p>
                    <p class="text-black text-2xl md:text-3xl font-medium">{{ $totalEvents }}</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-8 h-8 md:w-10 md:h-10 text-gray-300">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                </svg>
            </div>
            <div class="flex border border-gray-200 min-h-[120px] md:min-h-[150px] items-center justify-between shadow-md rounded-xl bg-white p-5 md:p-7 md:pt-10">
                <div class="flex flex-col gap-1">
                    <p class="text-gray-500 text-sm md:text-base">Success</p>
                    <p class="text-green-600 text-2xl md:text-3xl font-medium">{{ $successCount }}</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-8 h-8 md:w-10 md:h-10 text-green-300">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <div class="flex border border-gray-200 min-h-[120px] md:min-h-[150px] items-center justify-between shadow-md rounded-xl bg-white p-5 md:p-7 md:pt-10">
                <div class="flex flex-col gap-1">
                    <p class="text-gray-500 text-sm md:text-base">Errors</p>
                    <p class="text-orange-600 text-2xl md:text-3xl font-medium">{{ $errorCount }}</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-8 h-8 md:w-10 md:h-10 text-red-300">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <div class="flex border border-gray-200 min-h-[120px] md:min-h-[150px] items-center justify-between shadow-md rounded-xl bg-white p-5 md:p-7 md:pt-10">
                <div class="flex flex-col gap-1">
                    <p class="text-gray-500 text-sm md:text-base">Info</p>
                    <p class="text-blue-600 text-2xl md:text-3xl font-medium">{{ $infoCount }}</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-8 h-8 md:w-10 md:h-10 text-blue-300">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
            </div>
        </div>

        <div class="flex flex-col gap-4 md:gap-5 rounded-xl bg-white shadow-lg p-4 md:p-5 border border-gray-200">
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-5 h-5 md:w-6 md:h-6 text-blue-500">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                    </svg>
                    <p class="text-sm md:text-base text-black font-medium">Activity Timeline</p>
                </div>
                <p class="text-xs md:text-sm lg:text-base text-gray-500 font-normal">
                    Real-time system activity tracking
                </p>
            </div>

            <form method="GET" action="{{ route('system-logs.index') }}" class="w-full rounded-xl bg-gray-50 p-3 md:p-4 border border-gray-200 flex flex-col md:flex-row items-stretch md:items-center gap-2 md:gap-3">
                <input type="text" name="search" placeholder="Search logs..." value="{{ request('search') }}"
                    @keyup.enter="$el.form.submit()" @blur="$el.form.submit()"
                    class="bg-white rounded-md py-2 px-3 md:px-4 border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs md:text-sm w-full md:flex-1 lg:w-[500px] font-medium">


                <div x-data="{ open: false, selected: '{{ request('event_type', 'All Types') }}' }" class="relative w-full md:flex-1">
                    <input type="hidden" name="event_type" :value="selected !== 'All Types' ? selected : ''">
                    <button @click="open = !open" type="button"
                        class="bg-white w-full border border-gray-200 text-gray-700 text-xs md:text-sm rounded-md py-2 px-3 md:px-4 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center gap-2 whitespace-nowrap">
                        <span x-text="selected" class="font-medium"></span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="size-4 transition-transform ml-auto"
                            :class="{ 'rotate-180': open }">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                        class="absolute z-10 mt-2 bg-white border border-gray-200 rounded-md shadow-lg overflow-hidden w-full">
                        <ul class="py-1">
                            <li @click="selected = 'All Types'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'All Types' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>All Types</span>
                                <svg x-show="selected === 'All Types'" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                            <li @click="selected = 'delete'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'delete' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>Delete</span>
                                <svg x-show="selected === 'delete'" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                            <li @click="selected = 'mass delete'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'mass delete' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>Mass Delete</span>
                                <svg x-show="selected === 'mass delete'" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                            <li @click="selected = 'migrate'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'migrate' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>Migrate</span>
                                <svg x-show="selected === 'migrate'" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                            <li @click="selected = 'capture'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'capture' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>Capture</span>
                                <svg x-show="selected === 'capture'" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                        </ul>
                    </div>
                </div>

                <div x-data="{ open: false, selected: '{{ request('status', 'All Status') }}' }" class="relative w-full md:flex-1">
                    <input type="hidden" name="status" :value="selected !== 'All Status' ? selected : ''">
                    <button @click="open = !open" type="button"
                        class="bg-white w-full border border-gray-200 text-gray-700 text-xs md:text-sm rounded-md py-2 px-3 md:px-4 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center gap-2 whitespace-nowrap">
                        <span x-text="selected" class="font-medium"></span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="size-4 transition-transform ml-auto"
                            :class="{ 'rotate-180': open }">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                        class="absolute z-10 mt-2 bg-white border border-gray-200 rounded-md shadow-lg overflow-hidden w-full">
                        <ul class="py-1">
                            <li @click="selected = 'All Status'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'All Status' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>All Status</span>
                                <svg x-show="selected === 'All Status'" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                            <li @click="selected = 'success'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'success' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>Success</span>
                                <svg x-show="selected === 'success'" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                            <li @click="selected = 'failed'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'failed' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>Failed</span>
                                <svg x-show="selected === 'failed'" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                            <li @click="selected = 'info'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'info' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>Info</span>
                                <svg x-show="selected === 'info'" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                            <li @click="selected = 'warning'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'warning' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>Warning</span>
                                <svg x-show="selected === 'warning'" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                        </ul>
                    </div>
                </div>
            </form>

            <div class="flex flex-col gap-3 md:gap-4">
                @forelse($logs as $log)
                    <div
                        class="flex flex-col sm:flex-row justify-between rounded-2xl border border-gray-200 cursor-default hover:shadow-md p-3 md:p-4 lg:p-5 gap-3 sm:gap-0">
                        <div class="flex gap-3 md:gap-4 lg:gap-5">
                            <div class="bg-gray-50 w-6 h-6 md:w-7 md:h-7 rounded-lg flex items-center justify-center flex-shrink-0">
                                @if($log->status === 'success')
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="w-4 h-4 md:w-5 md:h-5 text-green-600">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                @elseif($log->status === 'failed')
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="w-4 h-4 md:w-5 md:h-5 text-red-500">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                @elseif($log->status === 'info')
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="w-4 h-4 md:w-5 md:h-5 text-blue-600">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                @elseif($log->status === 'warning')
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="w-4 h-4 md:w-5 md:h-5 text-yellow-600">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                @endif
                            </div>
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <div class="rounded-lg 
                                        {{ $log->event_type === 'delete' || $log->event_type === 'mass delete' ? 'bg-red-50 border-red-200' : '' }}
                                        {{ $log->event_type === 'migrate' ? 'bg-blue-50 border-blue-200' : '' }}
                                        {{ $log->event_type === 'capture' ? 'bg-violet-50 border-violet-200' : '' }}
                                        border px-2 py-1 w-max">
                                        <p class="
                                            {{ $log->event_type === 'delete' || $log->event_type === 'mass delete' ? 'text-red-600' : '' }}
                                            {{ $log->event_type === 'migrate' ? 'text-blue-600' : '' }}
                                            {{ $log->event_type === 'capture' ? 'text-violet-600' : '' }}
                                            text-xs md:text-sm font-medium">{{ ucfirst($log->event_type) }}</p>
                                    </div>
                                    @if($log->module)
                                        <span class="font-bold hidden sm:inline">&#183;</span>
                                        <p class="text-gray-600 text-xs md:text-sm font-medium">{{ $log->module }}</p>
                                    @endif
                                </div>
                                <p class="text-black font-medium text-sm md:text-base">{{ $log->message }}</p>
                                <p class="text-xs text-gray-600">{{ $log->created_at->format('m/d/Y, g:i:s A') }}</p>
                            </div>
                        </div>
                        <div class="flex min-w-[200px] items-end flex-col gap-1">
                          <div class="rounded-lg 
                              {{ $log->status === 'success' ? 'bg-green-50 border-green-200 text-green-600' : '' }}
                              {{ $log->status === 'failed' ? 'bg-red-50 border-red-200 text-red-600' : '' }}
                              {{ $log->status === 'info' ? 'bg-blue-50 border-blue-200 text-blue-600' : '' }}
                              {{ $log->status === 'warning' ? 'bg-yellow-50 border-yellow-200 text-yellow-600' : '' }}
                              border px-2 py-1 h-max w-max">
                              <p class="text-xs md:text-sm font-medium">{{ ucfirst($log->status) }}</p>
                          </div>
                          <p class="text-gray-500 max-sm:text-xs text-sm">Dilakukan oleh: {{ $log->user->name }}</p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="size-12 mx-auto text-gray-400 mb-3">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <p class="text-gray-500 text-sm">No logs found matching your filters.</p>
                    </div>
                @endforelse
            </div>
        </div>
</x-app-layout>
