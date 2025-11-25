@php
    $total = $transactions->count();
    $pending = $transactions->where('status', 'pending')->count();
    $success = $transactions->where('status', 'success')->count();
    $failed = $transactions->where('status', 'failed')->count();
    $successRate = $total > 0 ? number_format(($success / $total) * 100, 1) : 0;
    $readyToMigrate = $pending;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-[14px] bg-[linear-gradient(135deg,#155DFC_0%,#4F39F6_100%)] shadow-[0_10px_15px_-3px_rgba(0,0,0,0.10),0_4px_6px_-4px_rgba(0,0,0,0.10)] flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-5 text-white">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                </svg>
            </div>
            <div class="flex flex-col gap-1">
                <p class="text-[16px] font-medium text-black">Migrate</p>
                <p class="text-sm font-medium text-gray-600">Transfer data to target database</p>
            </div>
        </div>
    </x-slot>

    <div class="flex flex-col gap-6 md:gap-8 lg:gap-10" x-data="{
        selectAll: false,
        selected: [],
        allTransactionIds: {{ $transactions->pluck('id')->toJson() }},
        showDeleteModal: false,
        showSingleDeleteModal: false,
        deleteTarget: null,
    
        selectAllTransactions() {
            if (this.selectAll) {
                this.selected = [...this.allTransactionIds];
            } else {
                this.selected = [];
            }
        },
    
        clearAll() {
            this.selected = [];
            this.selectAll = false;
        },

        confirmDelete() {
            this.showDeleteModal = true;
        },

        confirmSingleDelete(transactionId) {
            this.deleteTarget = transactionId;
            this.showSingleDeleteModal = true;
        },

        deleteSelected() {
            $refs.bulkDeleteForm.submit();
        },

        deleteSingle() {
            $refs.singleDeleteForm.submit();
        }
    }" x-init="$watch('selected', value => selectAll = value.length === allTransactionIds.length && allTransactionIds.length > 0)">
        <div
            class="w-full bg-gradient-to-r from-green-600 to-green-700 rounded-xl p-5 md:p-8 lg:p-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 lg:gap-4">
            <div class="flex flex-col gap-4 md:gap-5 w-full lg:w-auto">
                <div class="flex items-center gap-3 md:gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="#FFFFFF" class="w-5 h-5 md:w-6 md:h-6 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                    </svg>
                    <div class="flex flex-col">
                        <p class="text-white font-normal text-xs md:text-sm tracking-wide">Source Database</p>
                        <p class="text-white text-sm md:text-base lg:text-lg font-semibold">Select database to migrate from</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
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
                                class="bg-white/20 backdrop-blur-md border border-white/30 text-white text-xs md:text-sm rounded-lg focus:ring-white/50 focus:border-white/50 w-full p-2 md:p-2.5 text-left flex items-center justify-between">
                                <span x-text="selected"></span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="size-5 transition-transform"
                                    :class="{ 'rotate-180': open }">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <div x-show="open" @click.away="open = false" x-cloak
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                                class="absolute z-[999] w-full mt-2 bg-white border border-white/30 rounded-lg shadow-lg overflow-hidden max-h-60 overflow-y-auto">
                                <ul class="py-1">
                                    @forelse($databases as $db)
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
                                    @empty
                                    <li class="px-4 py-3 text-gray-500 text-sm text-center">
                                        No databases available
                                    </li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div
                class="flex flex-row sm:flex-row items-center justify-between sm:justify-start gap-6 sm:gap-8 md:gap-10 w-full lg:w-auto">
                <div class="flex flex-col gap-1">
                    <p class="text-white font-medium text-xs md:text-sm">Success Rate</p>
                    <p class="text-2xl md:text-3xl font-bold text-white self-end">{{ $successRate }} %</p>
                </div>
                <div class="flex flex-col">
                    <p class="text-white font-medium text-xs md:text-sm">Ready to Migrate</p>
                    <p class="text-2xl md:text-3xl font-bold text-white self-end">{{ $readyToMigrate }}</p>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span class="font-medium">{{ session('success') }}</span>
                </div>
                <button @click="show = false" class="text-green-600 hover:text-green-800">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @elseif($current_database_name)
            <div class="bg-green-50 border border-green-200 flex flex-col sm:flex-row gap-3 p-4 rounded-lg">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="green" class="size-6 flex-shrink-0">
                    <path fill-rule="evenodd"
                        d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                        clip-rule="evenodd" />
                </svg>
                <div class="flex flex-col gap-1">
                    <p class="text-base text-green-800 font-medium">Connected to</p>
                    <p class="text-lg text-green-800 font-bold">{{ $current_database_name }}</p>
                    <p class="text-green-800 font-normal text-sm">
                        Showing transactions from this database.
                    </p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5 lg:gap-7">
            <div
                class="flex border border-gray-200 min-h-[120px] md:min-h-[150px] items-center justify-between shadow-md rounded-xl bg-white p-5 md:p-7 md:pt-10">
                <div class="flex flex-col gap-1">
                    <p class="text-gray-500 text-sm md:text-base">Total</p>
                    <p class="text-black text-2xl md:text-3xl font-medium">{{ $total }}</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                    class="w-8 h-8 md:w-10 md:h-10 text-gray-300">
                    <path d="M21 6.375c0 2.692-4.03 4.875-9 4.875S3 9.067 3 6.375 7.03 1.5 12 1.5s9 2.183 9 4.875Z" />
                    <path
                        d="M12 12.75c2.685 0 5.19-.586 7.078-1.609a8.283 8.283 0 0 0 1.897-1.384c.016.121.025.244.025.368C21 12.817 16.97 15 12 15s-9-2.183-9-4.875c0-.124.009-.247.025-.368a8.285 8.285 0 0 0 1.897 1.384C6.809 12.164 9.315 12.75 12 12.75Z" />
                    <path
                        d="M12 16.5c2.685 0 5.19-.586 7.078-1.609a8.282 8.282 0 0 0 1.897-1.384c.016.121.025.244.025.368 0 2.692-4.03 4.875-9 4.875s-9-2.183-9-4.875c0-.124.009-.247.025-.368a8.284 8.284 0 0 0 1.897 1.384C6.809 15.914 9.315 16.5 12 16.5Z" />
                    <path
                        d="M12 20.25c2.685 0 5.19-.586 7.078-1.609a8.282 8.282 0 0 0 1.897-1.384c.016.121.025.244.025.368 0 2.692-4.03 4.875-9 4.875s-9-2.183-9-4.875c0-.124.009-.247.025-.368a8.284 8.284 0 0 0 1.897 1.384C6.809 19.664 9.315 20.25 12 20.25Z" />
                </svg>
            </div>
            <div
                class="flex border border-gray-200 min-h-[120px] md:min-h-[150px] items-center justify-between shadow-md rounded-xl bg-white p-5 md:p-7 md:pt-10">
                <div class="flex flex-col gap-1">
                    <p class="text-gray-500 text-sm md:text-base">Pending</p>
                    <p class="text-orange-600 text-2xl md:text-3xl font-medium">{{ $pending }}</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-8 h-8 md:w-10 md:h-10 text-orange-300">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <div
                class="flex border border-gray-200 min-h-[120px] md:min-h-[150px] items-center justify-between shadow-md rounded-xl bg-white p-5 md:p-7 md:pt-10">
                <div class="flex flex-col gap-1">
                    <p class="text-gray-500 text-sm md:text-base">Migrated</p>
                    <p class="text-green-600 text-2xl md:text-3xl font-medium">{{ $success }}</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-8 h-8 md:w-10 md:h-10 text-green-300">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <div
                class="flex border border-gray-200 min-h-[120px] md:min-h-[150px] items-center justify-between shadow-md rounded-xl bg-white p-5 md:p-7 md:pt-10">
                <div class="flex flex-col gap-1">
                    <p class="text-gray-500 text-sm md:text-base">Failed</p>
                    <p class="text-red-600 text-2xl md:text-3xl font-medium">{{ $failed }}</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-8 h-8 md:w-10 md:h-10 text-red-300">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        </div>

        <div class="flex flex-col gap-4 md:gap-5 rounded-xl bg-white shadow-lg p-4 md:p-5 border border-gray-200">
            <div class="flex max-sm:flex-col max-sm:items-start max-sm:gap-3 items-center justify-between">
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5 md:w-6 md:h-6 text-blue-500">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                        </svg>
                        <p class="text-sm md:text-base text-black font-medium">Transaction Manager</p>
                    </div>
                    <p class="text-xs md:text-sm lg:text-base text-gray-500 font-normal">Select and migrate
                        transactions to
                        <span class="font-semibold">{{ $current_database_name }}</span>
                    </p>
                </div>

                <div x-show="selected.length > 0" x-cloak class="flex self-end gap-2">
                    <button @click="confirmDelete()" type="button"
                        class="bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-4 md:px-5 py-2 md:py-2.5 rounded-lg transition flex items-center gap-2 text-sm md:text-base border border-red-300">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        <span>Remove <span x-text="selected.length"></span> Selected</span>
                    </button>
                    <button
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 md:px-6 py-2 md:py-2.5 rounded-lg transition flex items-center gap-2 text-sm md:text-base">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                        <span>Migrate <span x-text="selected.length"></span> Selected</span>
                    </button>
                </div>

                <!-- Delete Confirmation Modal -->
                <div x-show="showDeleteModal" x-cloak
                    class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog"
                    aria-modal="true">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <!-- Background overlay -->
                        <div x-show="showDeleteModal" x-transition:enter="ease-out duration-300"
                            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                            x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                            @click="showDeleteModal = false"></div>

                        <!-- Modal panel -->
                        <div x-show="showDeleteModal" x-transition:enter="ease-out duration-300"
                            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                            x-transition:leave="ease-in duration-200"
                            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div
                                        class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                        <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg"
                                            fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                    </div>
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                            Delete Transactions
                                        </h3>
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-500">
                                                Are you sure you want to delete <span class="font-semibold"
                                                    x-text="selected.length"></span> selected transaction(s)? This
                                                action cannot be undone.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                                <button type="button" @click="deleteSelected()"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                                    Delete
                                </button>
                                <button type="button" @click="showDeleteModal = false"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>


            </div>

            <form method="GET" action="{{ route('migrate.index') }}" id="filterForm"
                class="w-full rounded-xl bg-gray-50 p-3 md:p-4 border border-gray-200 flex flex-col md:flex-row items-stretch md:items-center gap-2 md:gap-3">
                <input type="text" name="search" placeholder="Search transactions..."
                    value="{{ request('search') }}" @keyup.enter="$el.form.submit()" @blur="$el.form.submit()"
                    class="bg-white rounded-md py-2 px-3 md:px-4 border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs md:text-sm w-full md:w-[250px] lg:w-[300px] font-medium">

                <!-- All Database Dropdown -->
                <div x-data="{ open: false, selected: '{{ request('source_db', 'All Database') }}' }" class="relative w-full md:w-auto">
                    <input type="hidden" name="source_db" :value="selected !== 'All Database' ? selected : ''">
                    <button @click="open = !open" type="button"
                        class="bg-white w-full md:min-w-[180px] lg:min-w-[200px] border border-gray-200 text-gray-700 text-xs md:text-sm rounded-md py-2 px-3 md:px-4 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center gap-2 whitespace-nowrap">
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
                        class="absolute z-[99] mt-2 bg-white border border-gray-200 rounded-md shadow-lg overflow-hidden w-full md:min-w-[200px]">
                        <ul class="py-1 max-h-[200px] md:max-h-none overflow-y-auto">
                            <li @click="selected = 'All Database'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'All Database' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>All Database</span>
                                <svg x-show="selected === 'All Database'" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                            @foreach ($filter_databases as $db)
                                <li @click="selected = '{{ $db }}'; $nextTick(() => $el.closest('form').submit())"
                                    :class="selected === '{{ $db }}' ? 'bg-blue-50' : ''"
                                    class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                    <span>{{ $db }}</span>
                                    <svg x-show="selected === '{{ $db }}'"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="2" stroke="currentColor" class="size-4 text-blue-600">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <!-- All Modules Dropdown -->
                <div x-data="{ open: false, selected: '{{ request('module', 'All Modules') }}' }" class="relative w-full md:w-auto">
                    <input type="hidden" name="module" :value="selected !== 'All Modules' ? selected : ''">
                    <button @click="open = !open" type="button"
                        class="bg-white border w-full md:min-w-[180px] lg:min-w-[200px] border-gray-200 text-gray-700 text-xs md:text-sm rounded-md py-2 px-3 md:px-4 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center gap-2 whitespace-nowrap">
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
                        class="absolute z-[99] mt-2 bg-white border border-gray-200 rounded-md shadow-lg overflow-hidden w-full">
                        <ul class="py-1 max-h-[250px] overflow-y-auto">
                            <li @click="selected = 'All Modules'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'All Modules' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>All Modules</span>
                                <svg x-show="selected === 'All Modules'" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                            @foreach ($modules as $module)
                                <li @click="selected = '{{ $module }}'; $nextTick(() => $el.closest('form').submit())"
                                    :class="selected === '{{ $module }}' ? 'bg-blue-50' : ''"
                                    class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                    <span>{{ $module }}</span>
                                    <svg x-show="selected === '{{ $module }}'"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="2" stroke="currentColor" class="size-4 text-blue-600">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <!-- All Status Dropdown -->
                <div x-data="{ open: false, selected: '{{ request('status', 'All Status') }}' }" class="relative w-full md:w-auto">
                    <input type="hidden" name="status" :value="selected !== 'All Status' ? selected : ''">
                    <button @click="open = !open" type="button"
                        class="bg-white w-full md:min-w-[140px] lg:min-w-[150px] border border-gray-200 text-gray-700 text-xs md:text-sm rounded-md py-2 px-3 md:px-4 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center gap-2 whitespace-nowrap">
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
                        class="absolute z-[99] mt-2 bg-white border border-gray-200 rounded-md shadow-lg overflow-hidden w-full">
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
                            <li @click="selected = 'Pending'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'Pending' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>Pending</span>
                                <svg x-show="selected === 'Pending'" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                            <li @click="selected = 'Success'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'Success' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>Success</span>
                                <svg x-show="selected === 'Success'" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                            <li @click="selected = 'Failed'; $nextTick(() => $el.closest('form').submit())"
                                :class="selected === 'Failed' ? 'bg-blue-50' : ''"
                                class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                <span>Failed</span>
                                <svg x-show="selected === 'Failed'" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    class="size-4 text-blue-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </li>
                        </ul>
                    </div>
                </div>

                <button type="button" @click="selectAll = true; selectAllTransactions()"
                    :disabled="!{{ $transactions->count() }}"
                    class="bg-white w-full md:flex-1 hover:bg-gray-100 p-2 border border-gray-200 rounded-lg text-xs md:text-sm text-black font-medium cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    Select All
                </button>
                <button type="button" @click="clearAll()"
                    class="bg-white w-full md:flex-1 hover:bg-gray-100 p-2 border border-gray-200 rounded-lg text-xs md:text-sm text-black font-medium cursor-pointer">
                    Clear
                </button>
            </form>

            {{-- table --}}
            <div class="w-full overflow-hidden border border-gray-200 rounded-lg">
                <div class="overflow-x-auto max-h-[400px] md:max-h-[500px] lg:max-h-[600px] overflow-y-auto">
                    <table class="w-full border-collapse min-w-[800px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gray-100 border-b border-gray-200">
                                <th class="p-2 md:p-4 text-left">
                                    <input type="checkbox" x-model="selectAll" @change="selectAllTransactions()"
                                        class="w-3.5 h-3.5 md:w-4 md:h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 cursor-pointer">
                                </th>
                                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">
                                    Transaction No</th>
                                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">Source
                                    DB</th>
                                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">Module
                                </th>
                                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">
                                    Description</th>
                                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">Date
                                </th>
                                <th class="p-2 md:p-4 text-center text-xs md:text-sm font-semibold text-gray-700">
                                    Status</th>
                                <th class="p-2 md:p-4 text-center text-xs md:text-sm font-semibold text-gray-700">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            @forelse ($transactions as $transaction)
                                <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                                    <td class="p-2 md:p-4">
                                        <input type="checkbox" x-model="selected"
                                            value="{{ $transaction->id }}"
                                            class="w-3.5 h-3.5 md:w-4 md:h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 cursor-pointer">
                                    </td>
                                    <td class="p-2 md:p-4 text-xs md:text-sm font-medium text-gray-900">
                                        {{ $transaction->transaction_no }}</td>
                                    <td class="p-2 md:p-4 text-xs md:text-sm text-gray-600">
                                        {{ $transaction->accurateDatabase?->db_name ?? 'N/A' }}</td>
                                    <td class="p-2 md:p-4 text-xs md:text-sm text-gray-600">
                                        {{ $transaction->module?->name ?? 'N/A' }}</td>
                                    <td class="p-2 md:p-4 text-xs md:text-sm text-gray-600 max-w-xs truncate">
                                        {{ $transaction->description }}</td>
                                    <td class="p-2 md:p-4 text-xs md:text-sm text-gray-600">
                                        {{ $transaction->captured_at }}</td>
                                    <td class="p-2 md:p-4 text-center">
                                        @if ($transaction->status === 'success')
                                            <span
                                                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                                    class="size-3">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                                Success
                                            </span>
                                        @elseif ($transaction->status === 'pending')
                                            <span
                                                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                                    class="size-3">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                                Pending
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                                    class="size-3">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M6 18 18 6M6 6l12 12" />
                                                </svg>
                                                Failed
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-2 md:p-4 text-center">
                                        <button @click="confirmSingleDelete({{ $transaction->id }})"
                                            class="inline-flex items-center justify-center p-1.5 md:p-2 rounded-lg hover:bg-red-50 text-red-600 hover:text-red-700 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                class="w-4 h-4 md:w-5 md:h-5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-8 text-center text-gray-500">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                class="w-12 h-12 text-gray-400">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                            </svg>
                                            <p class="text-sm font-medium">No transactions found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Single Delete Confirmation Modal -->
            <div x-show="showSingleDeleteModal" x-cloak
                class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog"
                aria-modal="true">
                <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <!-- Background overlay -->
                    <div x-show="showSingleDeleteModal" x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                        x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                        @click="showSingleDeleteModal = false; deleteTarget = null"></div>

                    <!-- Modal panel -->
                    <div x-show="showSingleDeleteModal" x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div
                                    class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                        Delete Transaction
                                    </h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500">
                                            Are you sure you want to delete this transaction? This action cannot be undone.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                            <form x-ref="singleDeleteForm" :action="`/migrate/${deleteTarget}`" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                                    Delete
                                </button>
                            </form>
                            <button type="button" @click="showSingleDeleteModal = false; deleteTarget = null"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden form for bulk delete -->
            <form x-ref="bulkDeleteForm" action="{{ route('migrate.destroyMultiple') }}" method="POST" class="hidden">
                @csrf
                @method('DELETE')
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
            </form>
        </div>
    </div>
</x-app-layout>
