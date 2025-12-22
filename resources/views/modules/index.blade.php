<x-app-layout>
    <x-slot name="title">Modules Management</x-slot>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-[14px] bg-[linear-gradient(135deg,#155DFC_0%,#4F39F6_100%)] shadow-[0_10px_15px_-3px_rgba(0,0,0,0.10),0_4px_6px_-4px_rgba(0,0,0,0.10)] flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-5 text-white">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                </svg>
            </div>
            <div class="flex flex-col gap-1">
                <p class="text-[16px] font-medium text-black">Modules</p>
                <p class="text-sm font-medium text-gray-600">Capture data from Accurate modules</p>
            </div>
        </div>
    </x-slot>

    <div class="flex flex-col gap-10" x-data="{
        capturing: false,
        progress: 0,
        currentModule: '',
        switchingDb: false,
        searchQuery: '',
        async captureData(moduleName, moduleSlug) {
            this.capturing = true;
            this.progress = 0;
            this.currentModule = moduleName;
    
            try {
                const progressInterval = setInterval(() => {
                    if (this.progress < 90) {
                        this.progress += Math.floor(Math.random() * 15) + 5;
                    }
                }, 300);
    
                const startDate = document.getElementById('start_date')?.value || '';
                const endDate = document.getElementById('end_date')?.value || '';
                const filterType = document.querySelector('input[name=\'filter_type\']:checked')?.value || 'range';
    
                const response = await fetch(`{{ url('/modules') }}/${moduleSlug}/capture`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        start_date: startDate,
                        end_date: endDate,
                        filter_type: filterType
                    })
                });
    
                const result = await response.json();
    
                clearInterval(progressInterval);
                this.progress = 100;
    
                setTimeout(() => {
                    this.capturing = false;
                    this.progress = 0;
                    this.currentModule = '';
    
                    if (result.success) {
                        window.location.reload();
                    } else {
                        alert(result.message || 'Failed to capture data');
                    }
                }, 1000);
    
            } catch (error) {
                console.error('Capture error:', error);
                this.capturing = false;
                this.progress = 0;
                alert('An error occurred while capturing data');
            }
        }
    }" x-init="$watch('searchQuery', () => {
        Array.from($refs.moduleGrid.children).forEach(card => {
            const name = card.dataset.moduleName?.toLowerCase() || '';
            const description = card.dataset.moduleDescription?.toLowerCase() || '';
            const matches = !searchQuery || name.includes(searchQuery.toLowerCase()) || description.includes(searchQuery.toLowerCase());
            card.style.display = matches ? '' : 'none';
        });
    })">
        {{-- Progress Modal --}}
        <div x-show="capturing" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center px-4"
            style="display: none;">
            <div class="bg-white rounded-2xl p-8 max-w-md w-full shadow-2xl"
                x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100">
                <div class="flex flex-col gap-6">
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <svg class="animate-spin h-12 w-12 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-lg font-semibold text-gray-900">Capturing Data...</p>
                            <p class="text-sm text-gray-600" x-text="currentModule"></p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Progress</span>
                            <span class="font-semibold text-blue-600" x-text="progress + '%'"></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div class="bg-gradient-to-r from-blue-600 to-blue-400 h-3 rounded-full transition-all duration-300 ease-out"
                                :style="`width: ${progress}%`"></div>
                        </div>
                    </div>

                    <div x-show="progress === 100" x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        class="flex items-center gap-2 text-green-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                            <path fill-rule="evenodd"
                                d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="font-semibold">Complete!</span>
                    </div>
                </div>
            </div>
        </div>
        <div
            class="w-full bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl p-5 md:p-8 lg:p-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-5 lg:gap-0">
            <div class="flex flex-col gap-5">
                <div class="flex items-center gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="#FFFFFF" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                    </svg>
                    <div class="flex flex-col">
                        <p class="text-white font-normal text-sm tracking-wide">Source Database</p>
                        <p class="text-white text-lg font-semibold">Select database to capture data from</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full">
                    <form id="dbSelectForm" action="{{ route('database.select') }}" method="POST"
                        class="w-full flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        @csrf
                        <input type="hidden" name="selected_db_json" id="selectedDbInput">

                        <div x-data="{
                            open: false,
                            selected: @js($current_database_name ?? 'Select Database'),
                            loading: false,
                            selectDb(dbId, dbAlias, dbData) {
                                this.selected = dbAlias;
                                this.open = false;
                                this.loading = true;
                                $dispatch('db-switching', { switching: true });
                        
                                // Clear filter inputs when switching database
                                document.getElementById('start_date').value = '';
                                document.getElementById('end_date').value = '';
                        
                                document.getElementById('selectedDbInput').value = JSON.stringify(dbData);
                                document.getElementById('dbSelectForm').submit();
                            }
                        }" @db-switching.window="switchingDb = $event.detail.switching"
                            class="relative w-full">
                            <button @click="open = !open" type="button" :disabled="loading"
                                class="bg-white/20 backdrop-blur-md border border-white/30 text-white text-sm rounded-lg focus:ring-white/50 focus:border-white/50 w-full p-2.5 text-left flex items-center justify-between disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="!loading" x-text="selected"></span>
                                <span x-show="loading" class="flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                            stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    Switching database...
                                </span>
                                <svg x-show="!loading" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                    class="size-5 transition-transform" :class="{ 'rotate-180': open }">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <div x-show="open" @click.away="open = false"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="absolute z-10 w-full mt-2 bg-white border border-white/30 rounded-lg shadow-lg overflow-hidden max-h-60 overflow-y-auto">
                                <ul class="py-1">
                                    @foreach ($databases as $db)
                                        <li @click="selectDb({{ $db['id'] }}, '{{ $db['alias'] }}', {{ json_encode($db) }})"
                                            :class="selected === '{{ $db['alias'] }}' ? 'bg-blue-50' : ''"
                                            class="px-4 py-2 text-black text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                            <span>{{ $db['alias'] }}</span>
                                            <svg x-show="selected === '{{ $db['alias'] }}'"
                                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="2" stroke="currentColor" class="size-5 text-blue-600">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5 sm:gap-10">
                <div class="flex flex-col gap-1">
                    <p class="text-white font-medium text-sm">Modules Captured</p>
                    <p class="text-2xl md:text-3xl font-bold text-white self-start sm:self-end">{{ $modules->count() }}
                    </p>
                </div>
                <div class="flex flex-col">
                    <p class="text-white font-medium text-sm">Total Transactions</p>
                    <p class="text-2xl md:text-3xl font-bold text-white self-start sm:self-end">
                        {{ $total_transactions }}</p>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-95" x-init="setTimeout(() => show = false, 5000)"
                class="w-full rounded-xl shadow-md border-2 bg-green-50 border-green-200 flex flex-col sm:flex-row gap-3 p-4 md:p-5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="green"
                    class="size-6 flex-shrink-0">
                    <path fill-rule="evenodd"
                        d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                        clip-rule="evenodd" />
                </svg>
                <div class="flex flex-col gap-1">
                    <p class="text-lg text-green-800 font-bold">{{ session('success') }}</p>
                    <p class="text-green-800 font-normal text-[15px]">
                        You can now capture module data from this database.
                    </p>
                </div>
            </div>
        @endif
        @if ($current_database_name)
            {{-- Date Filter Form --}}
            <div class="w-full bg-white rounded-xl shadow-md border border-gray-200 p-5" id="filter-section">
                <form method="GET" action="{{ route('modules.index') }}" class="flex flex-col gap-4">
                    {{-- Filter Type Selection --}}
                    <div class="flex items-center gap-4">
                        <label class="block text-sm font-semibold text-gray-700">Filter Type:</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="filter_type" value="range"
                                    {{ request('filter_type', 'range') === 'range' ? 'checked' : '' }}
                                    onchange="toggleFilterType(this.value)"
                                    class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm font-medium text-gray-700">Date Range</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="filter_type" value="equal"
                                    {{ request('filter_type') === 'equal' ? 'checked' : '' }}
                                    onchange="toggleFilterType(this.value)"
                                    class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm font-medium text-gray-700">Specific Date</span>
                            </label>
                        </div>
                    </div>

                    {{-- Date Inputs --}}
                    <div class="flex flex-col md:flex-row items-stretch md:items-end gap-4">
                        <div class="flex-1" id="start-date-container">
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">
                                <span id="start-date-label">Start Date</span>
                            </label>
                            <input type="date" name="start_date" id="start_date"
                                value="{{ request('start_date') }}"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex-1" id="end-date-container">
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End
                                Date</label>
                            <input type="date" name="end_date" id="end_date" value="{{ request('end_date') }}"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors flex items-center gap-2 whitespace-nowrap">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                                Apply Filter
                            </button>
                            @if (request('start_date') || request('end_date'))
                                <a href="{{ route('modules.index') }}"
                                    class="px-6 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                    Clear
                                </a>
                            @endif
                        </div>
                    </div>
                </form>

                <script>
                    function toggleFilterType(type) {
                        const endDateContainer = document.getElementById('end-date-container');
                        const startDateLabel = document.getElementById('start-date-label');
                        const endDateInput = document.getElementById('end_date');

                        if (type === 'equal') {
                            endDateContainer.style.display = 'none';
                            startDateLabel.textContent = 'Transaction Date';
                            endDateInput.value = '';
                        } else {
                            endDateContainer.style.display = 'block';
                            startDateLabel.textContent = 'Start Date';
                        }
                    }

                    // Initialize on page load
                    document.addEventListener('DOMContentLoaded', function() {
                        const filterType = document.querySelector('input[name="filter_type"]:checked')?.value || 'range';
                        toggleFilterType(filterType);
                    });
                </script>
                @if (request('start_date') || request('end_date'))
                    <div class="mt-3 flex items-center gap-2 text-sm text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <span>Filtered from <strong>{{ request('start_date') ?: 'beginning' }}</strong> to
                            <strong>{{ request('end_date') ?: 'now' }}</strong></span>
                    </div>
                @endif
            </div>

            {{-- <div
                class="w-full rounded-xl shadow-md border-2 bg-green-50 border-green-200 flex flex-col sm:flex-row gap-3 p-4 md:p-5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="green"
                    class="size-6 flex-shrink-0">
                    <path fill-rule="evenodd"
                        d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                        clip-rule="evenodd" />
                </svg>
                <div class="flex flex-col gap-1">
                    <p class="text-base text-green-800 font-medium">Connected to</p>
                    <p class="text-lg text-green-800 font-bold">{{ $current_database_name }}</p>
                    <p class="text-green-800 font-normal text-[15px]">
                        You can now capture module data.
                    </p>
                </div>
            </div> --}}
        @endif

        {{-- Search Bar --}}
        <div class="w-full">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-5 h-5 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
                <input type="text" x-model="searchQuery" placeholder="Search modules by name or description..."
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                <div x-show="searchQuery" @click="searchQuery = ''"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-5 h-5 text-gray-400 hover:text-gray-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5" x-ref="moduleGrid">
            @php
                // Color mapping untuk inline styles
                $colorMap = [
                    'blue' => [
                        'bg' => '#2563eb',
                        'bgHover' => '#1d4ed8',
                        'from' => '#2563eb',
                        'to' => '#60a5fa',
                        'fromHover' => '#1d4ed8',
                        'toHover' => '#3b82f6',
                    ],
                    'cyan' => [
                        'bg' => '#0891b2',
                        'bgHover' => '#0e7490',
                        'from' => '#0891b2',
                        'to' => '#22d3ee',
                        'fromHover' => '#0e7490',
                        'toHover' => '#06b6d4',
                    ],
                    'violet' => [
                        'bg' => '#7c3aed',
                        'bgHover' => '#6d28d9',
                        'from' => '#7c3aed',
                        'to' => '#a78bfa',
                        'fromHover' => '#6d28d9',
                        'toHover' => '#8b5cf6',
                    ],
                    'orange' => [
                        'bg' => '#ea580c',
                        'bgHover' => '#c2410c',
                        'from' => '#ea580c',
                        'to' => '#fb923c',
                        'fromHover' => '#c2410c',
                        'toHover' => '#f97316',
                    ],
                    'red' => [
                        'bg' => '#dc2626',
                        'bgHover' => '#b91c1c',
                        'from' => '#dc2626',
                        'to' => '#f87171',
                        'fromHover' => '#b91c1c',
                        'toHover' => '#ef4444',
                    ],
                    'emerald' => [
                        'bg' => '#059669',
                        'bgHover' => '#047857',
                        'from' => '#059669',
                        'to' => '#34d399',
                        'fromHover' => '#047857',
                        'toHover' => '#10b981',
                    ],
                    'purple' => [
                        'bg' => '#9333ea',
                        'bgHover' => '#7e22ce',
                        'from' => '#9333ea',
                        'to' => '#c084fc',
                        'fromHover' => '#7e22ce',
                        'toHover' => '#a855f7',
                    ],
                    'green' => [
                        'bg' => '#16a34a',
                        'bgHover' => '#15803d',
                        'from' => '#16a34a',
                        'to' => '#4ade80',
                        'fromHover' => '#15803d',
                        'toHover' => '#22c55e',
                    ],
                    'indigo' => [
                        'bg' => '#4f46e5',
                        'bgHover' => '#4338ca',
                        'from' => '#4f46e5',
                        'to' => '#818cf8',
                        'fromHover' => '#4338ca',
                        'toHover' => '#6366f1',
                    ],
                    'pink' => [
                        'bg' => '#db2777',
                        'bgHover' => '#be185d',
                        'from' => '#db2777',
                        'to' => '#f472b6',
                        'fromHover' => '#be185d',
                        'toHover' => '#ec4899',
                    ],
                    'teal' => [
                        'bg' => '#0d9488',
                        'bgHover' => '#0f766e',
                        'from' => '#0d9488',
                        'to' => '#2dd4bf',
                        'fromHover' => '#0f766e',
                        'toHover' => '#14b8a6',
                    ],
                    'yellow' => [
                        'bg' => '#ca8a04',
                        'bgHover' => '#a16207',
                        'from' => '#ca8a04',
                        'to' => '#fbbf24',
                        'fromHover' => '#a16207',
                        'toHover' => '#eab308',
                    ],
                ];

                $moduleCards = [
                    [
                        'name' => 'Bank Transfer',
                        'slug' => 'bank-transfer',
                        'color' => 'blue',
                        'description' => 'Inter-bank transfers',
                        'scope' => 'bank_transfer_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />',
                    ],
                    [
                        'name' => 'Bill of Material',
                        'slug' => 'bill-of-material',
                        'color' => 'cyan',
                        'description' => 'Product recipes and components',
                        'scope' => 'bill_of_material_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />',
                    ],
                    [
                        'name' => 'Branch',
                        'slug' => 'branch',
                        'color' => 'cyan',
                        'description' => 'Company branches and locations',
                        'scope' => 'branch_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />',
                    ],
                    [
                        'name' => 'Currency',
                        'slug' => 'currency',
                        'color' => 'orange',
                        'description' => 'Currency master data',
                        'scope' => 'currency_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />',
                    ],
                    [
                        'name' => 'Customer',
                        'slug' => 'customer',
                        'color' => 'cyan',
                        'description' => 'Customer master data and contacts',
                        'scope' => 'customer_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />',
                    ],
                    [
                        'name' => 'Customer Category',
                        'slug' => 'customer-category',
                        'color' => 'violet',
                        'description' => 'Customer classification categories',
                        'scope' => 'customer_category_view',
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
<path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />',
                    ],
                    [
                        'name' => 'Customer Claim',
                        'slug' => 'customer-claim',
                        'color' => 'red',
                        'description' => 'Customer claims and complaints',
                        'scope' => 'customer_claim_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />',
                    ],
                    [
                        'name' => 'Data Classification',
                        'slug' => 'data-classification',
                        'color' => 'emerald',
                        'description' => 'General data classifications',
                        'scope' => 'data_classification_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />',
                    ],
                    [
                        'name' => 'Delivery Order',
                        'slug' => 'delivery-order',
                        'color' => 'indigo',
                        'description' => 'Delivery orders and shipments',
                        'scope' => 'delivery_order_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />',
                    ],
                    [
                        'name' => 'Department',
                        'slug' => 'department',
                        'color' => 'violet',
                        'description' => 'Organization departments',
                        'scope' => 'department_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />',
                    ],
                    [
                        'name' => 'Employee',
                        'slug' => 'employee',
                        'color' => 'purple',
                        'description' => 'Employee master data',
                        'scope' => 'employee_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />',
                    ],
                    [
                        'name' => 'Exchange Invoice',
                        'slug' => 'exchange-invoice',
                        'color' => 'purple',
                        'description' => 'Foreign exchange invoices',
                        'scope' => 'exchange_invoice_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
                    ],
                    [
                        'name' => 'Expense Accrual',
                        'slug' => 'expense-accrual',
                        'color' => 'red',
                        'description' => 'Accrued expenses tracking',
                        'scope' => 'expense_accrual_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V13.5Zm0 2.25h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V18Zm2.498-6.75h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V13.5Zm0 2.25h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V18Zm2.504-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5Zm0 2.25h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V18Zm2.498-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5ZM8.25 6h7.5v2.25h-7.5V6ZM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.65 4.5 4.757V19.5a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V4.757c0-1.108-.806-2.057-1.907-2.185A48.507 48.507 0 0 0 12 2.25Z" />',
                    ],
                    [
                        'name' => 'FOB',
                        'slug' => 'fob',
                        'color' => 'teal',
                        'description' => 'Free on Board terms',
                        'scope' => 'fob_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />',
                    ],
                    [
                        'name' => 'GL Account',
                        'slug' => 'glaccount',
                        'color' => 'green',
                        'description' => 'Chart of accounts',
                        'scope' => 'glaccount_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />',
                    ],
                    [
                        'name' => 'Item',
                        'slug' => 'item',
                        'color' => 'amber',
                        'description' => 'Product and inventory items',
                        'scope' => 'item_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-2.25-1.313M21 7.5v2.25m0-2.25-2.25 1.313M3 7.5l2.25-1.313M3 7.5l2.25 1.313M3 7.5v2.25m9 3 2.25-1.313M12 12.75l-2.25-1.313M12 12.75V15m0 6.75 2.25-1.313M12 21.75V19.5m0 2.25-2.25-1.313m0-16.875L12 2.25l2.25 1.313M21 14.25v2.25l-2.25 1.313m-13.5 0L3 16.5v-2.25" />',
                    ],
                    [
                        'name' => 'Item Adjustment',
                        'slug' => 'item-adjustment',
                        'color' => 'orange',
                        'description' => 'Inventory adjustments and corrections',
                        'scope' => 'item_adjustment_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />',
                    ],
                    [
                        'name' => 'Item Category',
                        'slug' => 'item-category',
                        'color' => 'orange',
                        'description' => 'Product classification categories',
                        'scope' => 'item_category_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 0 0 2.25-2.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v2.25A2.25 2.25 0 0 0 6 10.5Zm0 9.75h2.25A2.25 2.25 0 0 0 10.5 18v-2.25a2.25 2.25 0 0 0-2.25-2.25H6a2.25 2.25 0 0 0-2.25 2.25V18A2.25 2.25 0 0 0 6 20.25Zm9.75-9.75H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75h-2.25A2.25 2.25 0 0 0 13.5 6v2.25a2.25 2.25 0 0 0 2.25 2.25Z" />',
                    ],
                    [
                        'name' => 'Item Transfer',
                        'slug' => 'item-transfer',
                        'color' => 'emerald',
                        'description' => 'Inter-warehouse item transfers',
                        'scope' => 'item_transfer_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />',
                    ],
                    [
                        'name' => 'Job Order',
                        'slug' => 'job-order',
                        'color' => 'blue',
                        'description' => 'Job order production tracking',
                        'scope' => 'job_order_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />',
                    ],
                    [
                        'name' => 'Journal Voucher',
                        'slug' => 'journal-voucher',
                        'color' => 'green',
                        'description' => 'General journal entries',
                        'scope' => 'journal_voucher_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
                    ],
                    [
                        'name' => 'Material Adjustment',
                        'slug' => 'material-adjustment',
                        'color' => 'teal',
                        'description' => 'Material usage adjustments',
                        'scope' => 'material_adjustment_view',
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
                    ],
                    [
                        'name' => 'Price Category',
                        'slug' => 'price-category',
                        'color' => 'teal',
                        'description' => 'Pricing tiers and categories',
                        'scope' => 'price_category_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="m9 14.25 6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />',
                    ],
                    [
                        'name' => 'Project',
                        'slug' => 'project',
                        'color' => 'pink',
                        'description' => 'Project management and tracking',
                        'scope' => 'project_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />',
                    ],
                    [
                        'name' => 'Purchase Invoice',
                        'slug' => 'purchase-invoice',
                        'color' => 'orange',
                        'description' => 'Purchase invoices and payables',
                        'scope' => 'purchase_invoice_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />',
                    ],
                    [
                        'name' => 'Purchase Order',
                        'slug' => 'purchase-order',
                        'color' => 'purple',
                        'description' => 'Purchase orders and vendor management',
                        'scope' => 'purchase_order_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />',
                    ],
                    [
                        'name' => 'Purchase Payment',
                        'slug' => 'purchase-payment',
                        'color' => 'red',
                        'description' => 'Vendor payment transactions',
                        'scope' => 'purchase_payment_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />',
                    ],
                    [
                        'name' => 'Purchase Requisition',
                        'slug' => 'purchase-requisition',
                        'color' => 'pink',
                        'description' => 'Purchase request and requisitions',
                        'scope' => 'purchase_requisition_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />',
                    ],
                    [
                        'name' => 'Purchase Return',
                        'slug' => 'purchase-return',
                        'color' => 'orange',
                        'description' => 'Purchase returns and debit notes',
                        'scope' => 'purchase_return_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3" />',
                    ],
                    [
                        'name' => 'Receive Item',
                        'slug' => 'receive-item',
                        'color' => 'teal',
                        'description' => 'Goods receipt and inventory receiving',
                        'scope' => 'receive_item_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m6 4.125 2.25 2.25m0 0 2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />',
                    ],
                    [
                        'name' => 'Roll Over',
                        'slug' => 'roll-over',
                        'color' => 'amber',
                        'description' => 'Period roll over transactions',
                        'scope' => 'roll_over_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />',
                    ],
                    [
                        'name' => 'Sales Invoice',
                        'slug' => 'sales-invoice',
                        'color' => 'green',
                        'description' => 'Sales invoices and billing',
                        'scope' => 'sales_invoice_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
                    ],
                    [
                        'name' => 'Sales Order',
                        'slug' => 'sales-order',
                        'color' => 'blue',
                        'description' => 'Sales orders and customer management',
                        'scope' => 'sales_order_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />',
                    ],
                    [
                        'name' => 'Sales Quotation',
                        'slug' => 'sales-quotation',
                        'color' => 'blue',
                        'description' => 'Sales quotations and proposals',
                        'scope' => 'sales_quotation_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
                    ],
                    [
                        'name' => 'Sales Receipt',
                        'slug' => 'sales-receipt',
                        'color' => 'indigo',
                        'description' => 'Customer payment receipts',
                        'scope' => 'sales_receipt_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />',
                    ],
                    [
                        'name' => 'Sales Return',
                        'slug' => 'sales-return',
                        'color' => 'violet',
                        'description' => 'Sales returns and credit notes',
                        'scope' => 'sales_return_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />',
                    ],
                    [
                        'name' => 'Shipment',
                        'slug' => 'shipment',
                        'color' => 'blue',
                        'description' => 'Shipping and logistics tracking',
                        'scope' => 'shipment_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />',
                    ],
                    [
                        'name' => 'Stock Opname Order',
                        'slug' => 'stock-opname-order',
                        'color' => 'violet',
                        'description' => 'Physical stock count orders',
                        'scope' => 'stock_opname_order_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />',
                    ],
                    [
                        'name' => 'Stock Opname Result',
                        'slug' => 'stock-opname-result',
                        'color' => 'purple',
                        'description' => 'Stock count results and variances',
                        'scope' => 'stock_opname_result_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25M9 16.5v.75m3-3v3M15 12v5.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
                    ],
                    [
                        'name' => 'Tax',
                        'slug' => 'tax',
                        'color' => 'red',
                        'description' => 'Tax configuration and rates',
                        'scope' => 'tax_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V13.5Zm0 2.25h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V18Zm2.498-6.75h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V13.5Zm0 2.25h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V18Zm2.504-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5Zm0 2.25h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V18Zm2.498-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5ZM8.25 6h7.5v2.25h-7.5V6ZM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.65 4.5 4.757V19.5a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V4.757c0-1.108-.806-2.057-1.907-2.185A48.507 48.507 0 0 0 12 2.25Z" />',
                    ],
                    [
                        'name' => 'Unit',
                        'slug' => 'unit',
                        'color' => 'purple',
                        'description' => 'Unit of measurement',
                        'scope' => 'unit_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />',
                    ],
                    [
                        'name' => 'Vendor',
                        'slug' => 'vendor',
                        'color' => 'blue',
                        'description' => 'Vendor master data and suppliers',
                        'scope' => 'vendor_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 3.129 3h17.742a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />',
                    ],
                    [
                        'name' => 'Vendor Category',
                        'slug' => 'vendor-category',
                        'color' => 'indigo',
                        'description' => 'Vendor classification categories',
                        'scope' => 'vendor_category_view',
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
<path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />',
                    ],
                    [
                        'name' => 'Vendor Claim',
                        'slug' => 'vendor-claim',
                        'color' => 'orange',
                        'description' => 'Vendor claims and disputes',
                        'scope' => 'vendor_claim_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />',
                    ],
                    [
                        'name' => 'Vendor Price',
                        'slug' => 'vendor-price',
                        'color' => 'emerald',
                        'description' => 'Vendor pricing agreements',
                        'scope' => 'vendor_price_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
                    ],
                    [
                        'name' => 'Warehouse',
                        'slug' => 'warehouse',
                        'color' => 'red',
                        'description' => 'Warehouse and storage locations',
                        'scope' => 'warehouse_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />',
                    ],
                    [
                        'name' => 'Work Order',
                        'slug' => 'work-order',
                        'color' => 'indigo',
                        'description' => 'Manufacturing work orders',
                        'scope' => 'work_order_view',
                        'icon' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />',
                    ],
                ];
            @endphp

            @foreach ($moduleCards as $card)
                @php
                    $moduleData = $modules->firstWhere('slug', $card['slug']);
                    $isCaptured = $moduleData !== null;
                    $transactionCount = $isCaptured ? $moduleData->transactions_count : 0;
                    $isActive = $isCaptured ? $moduleData->is_active : false;
                    $lastCaptured = $isCaptured ? $moduleData->updated_at : null;
                    $colors = $colorMap[$card['color']] ?? $colorMap['blue'];
                @endphp

                <div data-module-name="{{ $card['name'] }}" data-module-description="{{ $card['description'] }}"
                    class="rounded-xl border border-gray-200 min-h-[280px] md:min-h-[320px] shadow-xl bg-white flex flex-col justify-between flex-1 px-5 md:px-7 py-4 md:py-5">
                    <div class="flex flex-col gap-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center justify-center w-[60px] h-[60px] md:w-[70px] md:h-[70px] rounded-xl"
                                style="background-color: {{ $colors['bg'] }};">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="#FFF" class="size-8">
                                    {!! $card['icon'] !!}
                                </svg>
                            </div>
                            @if ($isCaptured)
                                @if ($isActive)
                                    <span
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"
                                            fill="currentColor" class="size-3">
                                            <path fill-rule="evenodd"
                                                d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm3.844-8.791a.75.75 0 0 0-1.188-.918l-3.7 4.79-1.649-1.833a.75.75 0 1 0-1.114 1.004l2.25 2.5a.75.75 0 0 0 1.15-.043l4.25-5.5Z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        Active
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"
                                            fill="currentColor" class="size-3">
                                            <path fill-rule="evenodd"
                                                d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM5.5 7.5A.5.5 0 0 1 6 7h4a.5.5 0 0 1 0 1H6a.5.5 0 0 1-.5-.5Z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        Inactive
                                    </span>
                                @endif
                            @endif
                        </div>
                        <div class="flex flex-col">
                            <p class="text-black font-semibold text-lg">{{ $card['name'] }}</p>
                            <p class="text-[15px] text-gray-500">{{ $card['description'] }}</p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4">
                        @if ($isCaptured)
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-gray-700 font-semibold text-sm">{{ $transactionCount }} transactions
                                </p>
                                <p class="text-gray-500 text-xs mt-1">Last captured:
                                    {{ $lastCaptured->diffForHumans() }}</p>
                            </div>
                            <button @click="captureData('{{ $card['name'] }}', '{{ $card['slug'] }}')"
                                :disabled="capturing || switchingDb"
                                class="flex items-center justify-center gap-3 text-white font-semibold px-4 py-2 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                                style="background: linear-gradient(to right, {{ $colors['from'] }}, {{ $colors['to'] }});"
                                onmouseover="this.style.background='linear-gradient(to right, {{ $colors['fromHover'] }}, {{ $colors['toHover'] }})'"
                                onmouseout="this.style.background='linear-gradient(to right, {{ $colors['from'] }}, {{ $colors['to'] }})'">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="size-5">
                                    <path fill-rule="evenodd"
                                        d="M4.755 10.059a7.5 7.5 0 0 1 12.548-3.364l1.903 1.903h-3.183a.75.75 0 1 0 0 1.5h4.992a.75.75 0 0 0 .75-.75V4.356a.75.75 0 0 0-1.5 0v3.18l-1.9-1.9A9 9 0 0 0 3.306 9.67a.75.75 0 1 0 1.45.388Zm15.408 3.352a.75.75 0 0 0-.919.53 7.5 7.5 0 0 1-12.548 3.364l-1.902-1.903h3.183a.75.75 0 0 0 0-1.5H2.984a.75.75 0 0 0-.75.75v4.992a.75.75 0 0 0 1.5 0v-3.18l1.9 1.9a9 9 0 0 0 15.059-4.035.75.75 0 0 0-.53-.918Z"
                                        clip-rule="evenodd" />
                                </svg>
                                Re-Capture Data
                            </button>
                        @else
                            <div class="bg-gray-50 rounded-xl p-3 text-center">
                                <p class="text-gray-500 font-medium text-[15px]">No data captured yet</p>
                            </div>
                            <button @click="captureData('{{ $card['name'] }}', '{{ $card['slug'] }}')"
                                :disabled="capturing || switchingDb"
                                class="flex items-center justify-center gap-3 text-white font-semibold px-4 py-2 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                                style="background: linear-gradient(to right, {{ $colors['from'] }}, {{ $colors['to'] }});"
                                onmouseover="this.style.background='linear-gradient(to right, {{ $colors['fromHover'] }}, {{ $colors['toHover'] }})'"
                                onmouseout="this.style.background='linear-gradient(to right, {{ $colors['from'] }}, {{ $colors['to'] }})'">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="size-5">
                                    <path fill-rule="evenodd"
                                        d="M12 2.25a.75.75 0 0 1 .75.75v11.69l3.22-3.22a.75.75 0 1 1 1.06 1.06l-4.5 4.5a.75.75 0 0 1-1.06 0l-4.5-4.5a.75.75 0 1 1 1.06-1.06l3.22 3.22V3a.75.75 0 0 1 .75-.75Zm-9 13.5a.75.75 0 0 1 .75.75v2.25a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5V16.5a.75.75 0 0 1 1.5 0v2.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V16.5a.75.75 0 0 1 .75-.75Z"
                                        clip-rule="evenodd" />
                                </svg>
                                Capture Data
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div
            class="w-full rounded-xl shadow-xl border-2 bg-blue-50 border-blue-200 flex flex-col sm:flex-row gap-3 p-4 md:p-5">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="blue" class="size-6">
                <path fill-rule="evenodd"
                    d="M9 4.5a.75.75 0 0 1 .721.544l.813 2.846a3.75 3.75 0 0 0 2.576 2.576l2.846.813a.75.75 0 0 1 0 1.442l-2.846.813a3.75 3.75 0 0 0-2.576 2.576l-.813 2.846a.75.75 0 0 1-1.442 0l-.813-2.846a3.75 3.75 0 0 0-2.576-2.576l-2.846-.813a.75.75 0 0 1 0-1.442l2.846-.813A3.75 3.75 0 0 0 7.466 7.89l.813-2.846A.75.75 0 0 1 9 4.5ZM18 1.5a.75.75 0 0 1 .728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 0 1 0 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 0 1-1.456 0l-.258-1.036a2.625 2.625 0 0 0-1.91-1.91l-1.036-.258a.75.75 0 0 1 0-1.456l1.036-.258a2.625 2.625 0 0 0 1.91-1.91l.258-1.036A.75.75 0 0 1 18 1.5ZM16.5 15a.75.75 0 0 1 .712.513l.394 1.183c.15.447.5.799.948.948l1.183.395a.75.75 0 0 1 0 1.422l-1.183.395c-.447.15-.799.5-.948.948l-.395 1.183a.75.75 0 0 1-1.422 0l-.395-1.183a1.5 1.5 0 0 0-.948-.948l-1.183-.395a.75.75 0 0 1 0-1.422l1.183-.395c.447-.15.799-.5.948-.948l.395-1.183A.75.75 0 0 1 16.5 15Z"
                    clip-rule="evenodd" />
            </svg>
            <div class="flex flex-col gap-1">
                <p class="text-base text-blue-800 font-medium">{{ $total_transactions }} transactions</p>
                <p class="text-blue-700 font-normal text-[15px]">captured from {{ $current_database_name }}
                    database(s). Ready to migrate to your
                    target database!</p>
            </div>
        </div>
    </div>
</x-app-layout>
