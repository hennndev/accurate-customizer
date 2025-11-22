@php
    $modules = [
        [
            'name' => 'General Ledger',
            'description' => 'Chart of accounts, journal entries',
            'icon' => 'general-ledger-icon.svg',
            'status' => 'not_captured',
            'color' => 'blue',
        ],
        [
            'name' => 'Accounts Payable',
            'description' => 'Vendors, bills, payments',
            'icon' => 'accounts-payable-icon.svg',
            'status' => 'captured',
            'color' => 'purple',
        ],
        [
            'name' => 'Accounts Receivable',
            'description' => 'Customers, invoices, payments',
            'icon' => 'accounts-receivable-icon.svg',
            'status' => 'not_captured',
            'color' => 'pink',
        ],
        [
            'name' => 'Inventory',
            'description' => 'Products, stock levels, adjustments',
            'icon' => 'inventory-icon.svg',
            'status' => 'captured',
            'color' => 'green',
        ],
        [
            'name' => 'Payroll',
            'description' => 'Employee salaries, taxes, deductions',
            'icon' => 'payroll-icon.svg',
            'status' => 'not_captured',
            'color' => 'orange',
        ],
        [
            'name' => 'Fixed Assets',
            'description' => 'Asset tracking, depreciation schedules',
            'icon' => 'fixed-assets-icon.svg',
            'status' => 'not_captured',
            'color' => 'red',
        ],
        [
            'name' => 'Purchasing',
            'description' => 'Purchase orders, vendor management',
            'icon' => 'purchasing-icon.svg',
            'status' => 'not_captured',
            'color' => 'teal',
        ],
        [
            'name' => 'Sales',
            'description' => 'Sales orders, customer management',
            'icon' => 'sales-icon.svg',
            'status' => 'not_captured',
            'color' => 'indigo',
        ],
        [
            'name' => 'Banking',
            'description' => 'Bank accounts, transactions, reconciliations',
            'icon' => 'banking-icon.svg',
            'status' => 'not_captured',
            'color' => 'cyan',
        ],
        [
            'name' => 'Projects',
            'description' => 'Project tracking, billing, expenses',
            'icon' => 'projects-icon.svg',
            'status' => 'not_captured',
            'color' => 'violet',
        ],
        [
            'name' => 'Manufacturing',
            'description' => 'Production orders, BOMs, work centers',
            'icon' => 'manufacturing-icon.svg',
            'status' => 'not_captured',
            'color' => 'amber',
        ],
        [
            'name' => 'Reporting',
            'description' => 'Financial reports, custom reports',
            'icon' => 'reporting-icon.svg',
            'status' => 'not_captured',
            'color' => 'emerald',
        ],
    ];
@endphp

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

    <div class="flex flex-col gap-10">
        <div class="w-full bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl p-5 md:p-8 lg:p-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-5 lg:gap-0">
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
                    <div x-data="{ open: false, selected: 'COMPANY 2024' }" class="relative w-full">
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
                            class="absolute z-10 w-full mt-2 bg-white border border-white/30 rounded-lg shadow-lg overflow-hidden">
                            <ul class="py-1">
                                <li @click="selected = 'COMPANY 2024'; open = false"
                                    :class="selected === 'COMPANY 2024' ? 'bg-blue-50' : ''"
                                    class="px-4 py-2 text-black text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                    <span>COMPANY 2024</span>
                                    <svg x-show="selected === 'COMPANY 2024'" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                        class="size-5 text-blue-600">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </li>
                                <li @click="selected = 'COMPANY 2025'; open = false"
                                    :class="selected === 'COMPANY 2025' ? 'bg-blue-50' : ''"
                                    class="px-4 py-2 text-black text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                    <span>COMPANY 2025</span>
                                    <svg x-show="selected === 'COMPANY 2025'" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                        class="size-5 text-blue-600">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </li>
                                <li @click="selected = 'COMPANY 2026'; open = false"
                                    :class="selected === 'COMPANY 2026' ? 'bg-blue-50' : ''"
                                    class="px-4 py-2 text-black text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                                    <span>COMPANY 2026</span>
                                    <svg x-show="selected === 'COMPANY 2026'" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                        class="size-5 text-blue-600">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <button
                        class="bg-white/20 backdrop-blur-md border border-white/30 text-white font-semibold px-4 py-2 rounded-lg hover:bg-white/30 transition whitespace-nowrap w-full sm:w-auto">
                        Connect
                    </button>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5 sm:gap-10">
                <div class="flex flex-col gap-1">
                    <p class="text-white font-medium text-sm">Modules Captured</p>
                    <p class="text-2xl md:text-3xl font-bold text-white self-start sm:self-end">2 / 12</p>
                </div>
                <div class="flex flex-col">
                    <p class="text-white font-medium text-sm">Total Transactions</p>
                    <p class="text-2xl md:text-3xl font-bold text-white self-start sm:self-end">93</p>
                </div>
            </div>
        </div>

        <div class="w-full rounded-xl shadow-md border-2 bg-green-50 border-green-200 flex flex-col sm:flex-row gap-3 p-4 md:p-5">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="green" class="size-6">
                <path fill-rule="evenodd"
                    d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    clip-rule="evenodd" />
            </svg>
            <div class="flex flex-col gap-1">
                <p class="text-base text-green-800 font-medium">Connected to</p>
                <p class="text-lg text-green-800 font-bold">COMPANY 2024</p>
                <p class="text-green-800 font-normal text-[15px]">
                    You can now capture module data.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">
            @foreach ($modules as $module)
                @php
                    $colorClasses = [
                        'blue' => [
                            'bg' => 'bg-blue-600',
                            'from' => 'from-blue-600',
                            'to' => 'to-blue-400',
                            'hover-from' => 'hover:from-blue-700',
                            'hover-to' => 'hover:to-blue-500',
                        ],
                        'purple' => [
                            'bg' => 'bg-purple-600',
                            'from' => 'from-purple-600',
                            'to' => 'to-purple-400',
                            'hover-from' => 'hover:from-purple-700',
                            'hover-to' => 'hover:to-purple-500',
                        ],
                        'pink' => [
                            'bg' => 'bg-pink-600',
                            'from' => 'from-pink-600',
                            'to' => 'to-pink-400',
                            'hover-from' => 'hover:from-pink-700',
                            'hover-to' => 'hover:to-pink-500',
                        ],
                        'green' => [
                            'bg' => 'bg-green-600',
                            'from' => 'from-green-600',
                            'to' => 'to-green-400',
                            'hover-from' => 'hover:from-green-700',
                            'hover-to' => 'hover:to-green-500',
                        ],
                        'orange' => [
                            'bg' => 'bg-orange-600',
                            'from' => 'from-orange-600',
                            'to' => 'to-orange-400',
                            'hover-from' => 'hover:from-orange-700',
                            'hover-to' => 'hover:to-orange-500',
                        ],
                        'red' => [
                            'bg' => 'bg-red-600',
                            'from' => 'from-red-600',
                            'to' => 'to-red-400',
                            'hover-from' => 'hover:from-red-700',
                            'hover-to' => 'hover:to-red-500',
                        ],
                        'teal' => [
                            'bg' => 'bg-teal-600',
                            'from' => 'from-teal-600',
                            'to' => 'to-teal-400',
                            'hover-from' => 'hover:from-teal-700',
                            'hover-to' => 'hover:to-teal-500',
                        ],
                        'indigo' => [
                            'bg' => 'bg-indigo-600',
                            'from' => 'from-indigo-600',
                            'to' => 'to-indigo-400',
                            'hover-from' => 'hover:from-indigo-700',
                            'hover-to' => 'hover:to-indigo-500',
                        ],
                        'cyan' => [
                            'bg' => 'bg-cyan-600',
                            'from' => 'from-cyan-600',
                            'to' => 'to-cyan-400',
                            'hover-from' => 'hover:from-cyan-700',
                            'hover-to' => 'hover:to-cyan-500',
                        ],
                        'violet' => [
                            'bg' => 'bg-violet-600',
                            'from' => 'from-violet-600',
                            'to' => 'to-violet-400',
                            'hover-from' => 'hover:from-violet-700',
                            'hover-to' => 'hover:to-violet-500',
                        ],
                        'amber' => [
                            'bg' => 'bg-amber-600',
                            'from' => 'from-amber-600',
                            'to' => 'to-amber-400',
                            'hover-from' => 'hover:from-amber-700',
                            'hover-to' => 'hover:to-amber-500',
                        ],
                        'emerald' => [
                            'bg' => 'bg-emerald-600',
                            'from' => 'from-emerald-600',
                            'to' => 'to-emerald-400',
                            'hover-from' => 'hover:from-emerald-700',
                            'hover-to' => 'hover:to-emerald-500',
                        ],
                    ];
                    $colors = $colorClasses[$module['color']];
                @endphp
                <div
                    class="rounded-xl border border-gray-200 min-h-[280px] md:min-h-[320px] shadow-xl bg-white flex flex-col justify-between flex-1 px-5 md:px-7 py-4 md:py-5">
                    <div class="flex flex-col gap-5">
                        <div class="flex items-center justify-center {{ $colors['bg'] }} w-[60px] h-[60px] md:w-[70px] md:h-[70px] rounded-xl">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#FFF"
                                class="size-8">
                                <path fill-rule="evenodd"
                                    d="M4.5 2.25a.75.75 0 0 0 0 1.5v16.5h-.75a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5h-.75V3.75a.75.75 0 0 0 0-1.5h-15ZM9 6a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H9Zm-.75 3.75A.75.75 0 0 1 9 9h1.5a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1-.75-.75ZM9 12a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H9Zm3.75-5.25A.75.75 0 0 1 13.5 6H15a.75.75 0 0 1 0 1.5h-1.5a.75.75 0 0 1-.75-.75ZM13.5 9a.75.75 0 0 0 0 1.5H15A.75.75 0 0 0 15 9h-1.5Zm-.75 3.75a.75.75 0 0 1 .75-.75H15a.75.75 0 0 1 0 1.5h-1.5a.75.75 0 0 1-.75-.75ZM9 19.5v-2.25a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v2.25a.75.75 0 0 1-.75.75h-4.5A.75.75 0 0 1 9 19.5Z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <p class="text-black font-semibold text-lg">{{ $module['name'] }}</p>
                            <p class="text-[15px] text-gray-500">
                                {{ $module['description'] }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4">
                        <div class="bg-gray-50 rounded-xl p-3 text-center">
                            <p class="text-gray-500 font-medium text-[15px]">No data captured yet</p>
                        </div>
                        <button
                            class="flex items-center justify-center gap-3 bg-gradient-to-r {{ $colors['from'] }} {{ $colors['to'] }} text-white font-semibold px-4 py-2 rounded-lg {{ $colors['hover-from'] }} {{ $colors['hover-to'] }} transition">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                class="size-5">
                                <path fill-rule="evenodd"
                                    d="M12 2.25a.75.75 0 0 1 .75.75v11.69l3.22-3.22a.75.75 0 1 1 1.06 1.06l-4.5 4.5a.75.75 0 0 1-1.06 0l-4.5-4.5a.75.75 0 1 1 1.06-1.06l3.22 3.22V3a.75.75 0 0 1 .75-.75Zm-9 13.5a.75.75 0 0 1 .75.75v2.25a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5V16.5a.75.75 0 0 1 1.5 0v2.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V16.5a.75.75 0 0 1 .75-.75Z"
                                    clip-rule="evenodd" />
                            </svg>
                            Capture Data
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="w-full rounded-xl shadow-xl border-2 bg-blue-50 border-blue-200 flex flex-col sm:flex-row gap-3 p-4 md:p-5">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="blue" class="size-6">
                <path fill-rule="evenodd"
                    d="M9 4.5a.75.75 0 0 1 .721.544l.813 2.846a3.75 3.75 0 0 0 2.576 2.576l2.846.813a.75.75 0 0 1 0 1.442l-2.846.813a3.75 3.75 0 0 0-2.576 2.576l-.813 2.846a.75.75 0 0 1-1.442 0l-.813-2.846a3.75 3.75 0 0 0-2.576-2.576l-2.846-.813a.75.75 0 0 1 0-1.442l2.846-.813A3.75 3.75 0 0 0 7.466 7.89l.813-2.846A.75.75 0 0 1 9 4.5ZM18 1.5a.75.75 0 0 1 .728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 0 1 0 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 0 1-1.456 0l-.258-1.036a2.625 2.625 0 0 0-1.91-1.91l-1.036-.258a.75.75 0 0 1 0-1.456l1.036-.258a2.625 2.625 0 0 0 1.91-1.91l.258-1.036A.75.75 0 0 1 18 1.5ZM16.5 15a.75.75 0 0 1 .712.513l.394 1.183c.15.447.5.799.948.948l1.183.395a.75.75 0 0 1 0 1.422l-1.183.395c-.447.15-.799.5-.948.948l-.395 1.183a.75.75 0 0 1-1.422 0l-.395-1.183a1.5 1.5 0 0 0-.948-.948l-1.183-.395a.75.75 0 0 1 0-1.422l1.183-.395c.447-.15.799-.5.948-.948l.395-1.183A.75.75 0 0 1 16.5 15Z"
                    clip-rule="evenodd" />
            </svg>
            <div class="flex flex-col gap-1">
                <p class="text-base text-blue-800 font-medium">194 transactions</p>
                <p class="text-blue-700 font-normal text-[15px]">captured from 1 database(s). Ready to migrate to your
                    target database!</p>
            </div>
        </div>
    </div>
</x-app-layout>
