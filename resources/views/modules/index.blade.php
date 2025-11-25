<x-app-layout>
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
        async captureData(moduleName, moduleSlug) {
            this.capturing = true;
            this.progress = 0;
            this.currentModule = moduleName;
            
            try {
                // Simulate progress while API call is happening
                const progressInterval = setInterval(() => {
                    if (this.progress < 90) {
                        this.progress += Math.floor(Math.random() * 15) + 5;
                    }
                }, 300);

                // Call API untuk capture data
                const response = await fetch(`{{ url('/modules') }}/${moduleSlug}/capture`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const result = await response.json();
                
                clearInterval(progressInterval);
                this.progress = 100;

                setTimeout(() => {
                    this.capturing = false;
                    this.progress = 0;
                    this.currentModule = '';
                    
                    if (result.success) {
                        window.location.reload(); // Reload untuk update count
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
    }">
        {{-- Progress Modal --}}
        <div x-show="capturing" 
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center px-4"
            style="display: none;">
            <div class="bg-white rounded-2xl p-8 max-w-md w-full shadow-2xl"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100">
                <div class="flex flex-col gap-6">
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <svg class="animate-spin h-12 w-12 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
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

                    <div x-show="progress === 100" 
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="flex items-center gap-2 text-green-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
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
                    <form id="dbSelectForm" action="{{ route('database.select') }}" method="POST" class="w-full flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        @csrf
                        <input type="hidden" name="selected_db_json" id="selectedDbInput">
                        
                        <div x-data="{ 
                            open: false, 
                            selected: @js($current_database_name ?? 'Select Database'),
                            selectDb(dbId, dbAlias, dbData) {
                                this.selected = dbAlias;
                                this.open = false;
                                document.getElementById('selectedDbInput').value = JSON.stringify(dbData);
                                document.getElementById('dbSelectForm').submit();
                            }
                        }" class="relative w-full">
                            <button @click="open = !open" type="button"
                                class="bg-white/20 backdrop-blur-md border border-white/30 text-white text-sm rounded-lg focus:ring-white/50 focus:border-white/50 w-full p-2.5 text-left flex items-center justify-between">
                                <span x-text="selected"></span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="size-5 transition-transform"
                                    :class="{ 'rotate-180': open }">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <div x-show="open" @click.away="open = false"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                                class="absolute z-10 w-full mt-2 bg-white border border-white/30 rounded-lg shadow-lg overflow-hidden max-h-60 overflow-y-auto">
                                <ul class="py-1">
                                    @foreach($databases as $db)
                                    <li @click="selectDb({{ $db['id'] }}, '{{ $db['alias'] }}', {{ json_encode($db) }})"
                                        :class="selected === '{{ $db['alias'] }}' ? 'bg-blue-50' : ''"
                                        class="px-4 py-2 text-black text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                        <span>{{ $db['alias'] }}</span>
                                        <svg x-show="selected === '{{ $db['alias'] }}'" xmlns="http://www.w3.org/2000/svg"
                                            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                            class="size-5 text-blue-600">
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
                    <p class="text-2xl md:text-3xl font-bold text-white self-start sm:self-end">{{ $modules->count() }}</p>
                </div>
                <div class="flex flex-col">
                    <p class="text-white font-medium text-sm">Total Transactions</p>
                    <p class="text-2xl md:text-3xl font-bold text-white self-start sm:self-end">{{ $total_transactions }}</p>
                </div>
            </div>
        </div>

        @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95" x-init="setTimeout(() => show = false, 5000)"
            class="w-full rounded-xl shadow-md border-2 bg-green-50 border-green-200 flex flex-col sm:flex-row gap-3 p-4 md:p-5">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="green" class="size-6 flex-shrink-0">
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
        @elseif($current_database_name)
        <div
            class="w-full rounded-xl shadow-md border-2 bg-green-50 border-green-200 flex flex-col sm:flex-row gap-3 p-4 md:p-5">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="green" class="size-6 flex-shrink-0">
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
        </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">
            @php
                $moduleCards = [
                    ['name' => 'Sales Order', 'slug' => 'sales-order', 'color' => 'blue', 'description' => 'Sales orders and customer management'],
                    ['name' => 'Purchase Order', 'slug' => 'purchase-order', 'color' => 'purple', 'description' => 'Purchase orders and vendor management'],
                    ['name' => 'Sales Invoice', 'slug' => 'sales-invoice', 'color' => 'green', 'description' => 'Sales invoices and billing'],
                    ['name' => 'Purchase Invoice', 'slug' => 'purchase-invoice', 'color' => 'orange', 'description' => 'Purchase invoices and payables'],
                    ['name' => 'Delivery Order', 'slug' => 'delivery-order', 'color' => 'indigo', 'description' => 'Delivery orders and shipments'],
                    ['name' => 'Receive Item', 'slug' => 'receive-item', 'color' => 'teal', 'description' => 'Goods receipt and inventory receiving'],
                    ['name' => 'Customer', 'slug' => 'customer', 'color' => 'cyan', 'description' => 'Customer master data and contacts'],
                    ['name' => 'Item', 'slug' => 'item', 'color' => 'amber', 'description' => 'Product and inventory items'],
                    ['name' => 'Branch', 'slug' => 'branch', 'color' => 'cyan', 'description' => 'Company branches and locations'],
                    ['name' => 'Department', 'slug' => 'department', 'color' => 'violet', 'description' => 'Organization departments'],
                    ['name' => 'Employee', 'slug' => 'employee', 'color' => 'purple', 'description' => 'Employee master data'],
                    ['name' => 'Fixed Asset', 'slug' => 'fixed-asset', 'color' => 'emerald', 'description' => 'Fixed assets and depreciation'],
                    ['name' => 'Warehouse', 'slug' => 'warehouse', 'color' => 'red', 'description' => 'Warehouse and storage locations'],
                    ['name' => 'Vendor', 'slug' => 'vendor', 'color' => 'blue', 'description' => 'Vendor master data and suppliers'],
                    ['name' => 'Project', 'slug' => 'project', 'color' => 'pink', 'description' => 'Project management and tracking'],
                ];
            @endphp

            @foreach($moduleCards as $card)
                @php
                    $moduleData = $modules->firstWhere('slug', $card['slug']);
                    $isCaptured = $moduleData !== null;
                    $transactionCount = $isCaptured ? $moduleData->transactions_count : 0;
                    $isActive = $isCaptured ? $moduleData->is_active : false;
                    $lastCaptured = $isCaptured ? $moduleData->updated_at : null;
                @endphp

                <div class="rounded-xl border border-gray-200 min-h-[280px] md:min-h-[320px] shadow-xl bg-white flex flex-col justify-between flex-1 px-5 md:px-7 py-4 md:py-5">
                    <div class="flex flex-col gap-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center justify-center bg-{{ $card['color'] }}-600 w-[60px] h-[60px] md:w-[70px] md:h-[70px] rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#FFF" class="size-8">
                                    <path fill-rule="evenodd"
                                        d="M4.5 2.25a.75.75 0 0 0 0 1.5v16.5h-.75a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5h-.75V3.75a.75.75 0 0 0 0-1.5h-15ZM9 6a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H9Zm-.75 3.75A.75.75 0 0 1 9 9h1.5a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1-.75-.75ZM9 12a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H9Zm3.75-5.25A.75.75 0 0 1 13.5 6H15a.75.75 0 0 1 0 1.5h-1.5a.75.75 0 0 1-.75-.75ZM13.5 9a.75.75 0 0 0 0 1.5H15A.75.75 0 0 0 15 9h-1.5Zm-.75 3.75a.75.75 0 0 1 .75-.75H15a.75.75 0 0 1 0 1.5h-1.5a.75.75 0 0 1-.75-.75ZM9 19.5v-2.25a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v2.25a.75.75 0 0 1-.75.75h-4.5A.75.75 0 0 1 9 19.5Z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            @if($isCaptured)
                                @if($isActive)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-3">
                                            <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm3.844-8.791a.75.75 0 0 0-1.188-.918l-3.7 4.79-1.649-1.833a.75.75 0 1 0-1.114 1.004l2.25 2.5a.75.75 0 0 0 1.15-.043l4.25-5.5Z" clip-rule="evenodd" />
                                        </svg>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-3">
                                            <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM5.5 7.5A.5.5 0 0 1 6 7h4a.5.5 0 0 1 0 1H6a.5.5 0 0 1-.5-.5Z" clip-rule="evenodd" />
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
                        @if($isCaptured)
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-gray-700 font-semibold text-sm">{{ $transactionCount }} transactions</p>
                                <p class="text-gray-500 text-xs mt-1">Last captured: {{ $lastCaptured->diffForHumans() }}</p>
                            </div>
                            <button @click="captureData('{{ $card['name'] }}', '{{ $card['slug'] }}')"
                                class="flex items-center justify-center gap-3 bg-gradient-to-r from-{{ $card['color'] }}-600 to-{{ $card['color'] }}-400 text-white font-semibold px-4 py-2 rounded-lg hover:from-{{ $card['color'] }}-700 hover:to-{{ $card['color'] }}-500 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5">
                                    <path fill-rule="evenodd" d="M4.755 10.059a7.5 7.5 0 0 1 12.548-3.364l1.903 1.903h-3.183a.75.75 0 1 0 0 1.5h4.992a.75.75 0 0 0 .75-.75V4.356a.75.75 0 0 0-1.5 0v3.18l-1.9-1.9A9 9 0 0 0 3.306 9.67a.75.75 0 1 0 1.45.388Zm15.408 3.352a.75.75 0 0 0-.919.53 7.5 7.5 0 0 1-12.548 3.364l-1.902-1.903h3.183a.75.75 0 0 0 0-1.5H2.984a.75.75 0 0 0-.75.75v4.992a.75.75 0 0 0 1.5 0v-3.18l1.9 1.9a9 9 0 0 0 15.059-4.035.75.75 0 0 0-.53-.918Z" clip-rule="evenodd" />
                                </svg>
                                Re-Capture Data
                            </button>
                        @else
                            <div class="bg-gray-50 rounded-xl p-3 text-center">
                                <p class="text-gray-500 font-medium text-[15px]">No data captured yet</p>
                            </div>
                            <button @click="captureData('{{ $card['name'] }}', '{{ $card['slug'] }}')"
                                class="flex items-center justify-center gap-3 bg-gradient-to-r from-{{ $card['color'] }}-600 to-{{ $card['color'] }}-400 text-white font-semibold px-4 py-2 rounded-lg hover:from-{{ $card['color'] }}-700 hover:to-{{ $card['color'] }}-500 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5">
                                    <path fill-rule="evenodd" d="M12 2.25a.75.75 0 0 1 .75.75v11.69l3.22-3.22a.75.75 0 1 1 1.06 1.06l-4.5 4.5a.75.75 0 0 1-1.06 0l-4.5-4.5a.75.75 0 1 1 1.06-1.06l3.22 3.22V3a.75.75 0 0 1 .75-.75Zm-9 13.5a.75.75 0 0 1 .75.75v2.25a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5V16.5a.75.75 0 0 1 1.5 0v2.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V16.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
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
                <p class="text-blue-700 font-normal text-[15px]">captured from {{ $current_database_name }} database(s). Ready to migrate to your
                    target database!</p>
            </div>
        </div>
    </div>
</x-app-layout>
