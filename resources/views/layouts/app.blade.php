<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] {
            display: none !important;
        }

        .sidebar {
            transition: all 0.3s ease;
        }

        .sidebar.open {
            width: 16.5rem;
            transform: translateX(0);
        }

        .sidebar.closed {
            width: 5rem;
            transform: translateX(-100%);
        }

        @media (min-width: 1024px) {
            .sidebar.closed {
                transform: translateX(0);
                width: 6rem;
            }
        }

        @media (max-width: 1023px) {
            .sidebar.closed {
                transform: translateX(-100%);
            }
        }

        .overlay {
            transition: opacity 0.3s ease;
        }

        .overlay.show {
            opacity: 1;
            display: block;
        }

        .overlay.hide {
            opacity: 0;
            display: none;
        }

        /* Hide text when sidebar closed on desktop */
        @media (min-width: 1024px) {

            .sidebar.closed .sidebar-text,
            .sidebar.closed .sidebar-section-title {
                display: none;
            }

            .sidebar.closed .sidebar-link {
                justify-content: center;
            }
        }

        /* Active menu styling */
        .menu-item.active {
            background-color: #EFF6FF;
            border-radius: 8px;
        }

        .menu-item.active svg {
            color: #2563EB;
        }

        .menu-item.active p {
            color: #2563EB;
            font-weight: 500;
        }

        .menu-item {
            transition: all 0.2s ease;
            padding: 10px 12px;
            border-radius: 8px;
        }

        .menu-item:hover {
            background-color: #F3F4F6;
        }

        .menu-item.active:hover {
            background-color: #EFF6FF;
        }
    </style>
</head>

<body class="font-sans antialiased bg-gray-50">
    <div class="flex h-screen bg-gray-50">
        <div id="overlay" class="overlay hide fixed inset-0 bg-[rgba(0,0,0,0.4)] z-20 lg:hidden"
            onclick="toggleSidebar()"></div>

        <aside id="sidebar"
            class="sidebar bg-[#FAFAFA] border-r border-gray-200 fixed lg:relative inset-y-0 left-0 z-30 overflow-y-auto">
            <div class="flex items-center gap-3 p-6 border-b border-gray-200">
                <div
                    class="flex items-center justify-center w-11 h-11 rounded-[14px] bg-[linear-gradient(135deg,#155DFC_0%,#4F39F6_100%)] shadow-[0_10px_15px_-3px_rgba(0,0,0,0.10),0_4px_6px_-4px_rgba(0,0,0,0.10)] flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="size-6 text-white">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                    </svg>
                </div>
                <div class="flex flex-col sidebar-text">
                    <p class="text-black text-[16px] font-medium">Accurate</p>
                    <p class="text-gray-600 text-[14px]">Migration Tool</p>
                </div>
            </div>

            <div class="p-7">
                <div class="flex flex-col gap-7">
                    <div class="flex flex-col gap-3">
                        <p class="text-[12px] text-gray-600 font-medium leading-4 sidebar-section-title">MAIN MENU</p>
                        <div class="flex flex-col">
                            <a href="{{ route('modules.index') }}"
                                class="menu-item sidebar-link flex items-center gap-3 {{ request()->routeIs('modules.*') ? 'active' : '' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="size-5 flex-shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                </svg>
                                <p class="text-sm sidebar-text font-medium text-black">Module</p>
                            </a>
                            <a href="{{ route('migrate.index') }}"
                                class="menu-item sidebar-link flex items-center gap-3 {{ request()->routeIs('migrate.*') ? 'active' : '' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="size-5 flex-shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                </svg>
                                <p class="text-sm sidebar-text font-medium text-black">Migrate</p>
                            </a>
                            <a href="{{ route('system-logs.index') }}"
                                class="menu-item sidebar-link flex items-center gap-3 {{ request()->routeIs('system-logs.*') ? 'active' : '' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="size-5 flex-shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <p class="text-sm sidebar-text font-medium text-black">System Logs</p>
                            </a>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3">
                        <p class="text-[12px] text-gray-600 font-medium leading-4 sidebar-section-title">SETTINGS</p>
                        <div class="flex flex-col">
                            @can('manage_settings')
                                <a href="{{ route('configuration.index') }}"
                                    class="menu-item sidebar-link flex items-center gap-3 {{ request()->routeIs('configuration.*') ? 'active' : '' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-5 flex-shrink-0">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                    </svg>
                                    <p class="text-sm sidebar-text font-medium text-black">Configuration</p>
                                </a>
                            @endcan
                            @can('manage_users')
                                <a href="{{ route('users.index') }}"
                                    class="menu-item sidebar-link flex items-center gap-3 {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="size-5 flex-shrink-0">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                    </svg>
                                    <p class="text-sm sidebar-text font-medium text-black">Users</p>
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main content area -->
        <div class="flex-1 flex flex-col overflow-hidden min-w-0">
            <header class="bg-white border-b border-gray-200 py-5 px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center min-w-0 flex-1">
                        <button onclick="toggleSidebar()" class="text-gray-500 focus:outline-none flex-shrink-0 mr-5">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="size-6 cursor-pointer">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>
                        {{ $header }}
                    </div>
                    <div class="flex items-center space-x-3 sm:space-x-5 flex-shrink-0">
                        <div class="relative">
                            <button onclick="toggleDropdown()"
                                class="flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none transition duration-150 ease-in-out">
                                <img class="h-8 w-8 rounded-full object-cover"
                                    src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=4F46E5&color=fff"
                                    alt="{{ Auth::user()->name }}">
                            </button>
                            <div id="dropdown"
                                class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                                <div class="block px-4 py-2 text-xs text-gray-400">Manage Account</div>
                                <x-dropdown-link href="{{ route('profile.edit') }}">{{ __('Profile') }}
                                </x-dropdown-link>
                                <div class="border-t border-gray-200"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full text-left px-4 py-2">
                                        {{ __('Log Out') }}
                                    </button>
                                </form>

                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-white p-4 sm:p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    @stack('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    {{-- SweetAlert Notifications --}}
    @if (session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '{{ session('success') }}',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                toast: true,
                position: 'top-end',
                customClass: {
                    popup: 'colored-toast'
                }
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '{{ session('error') }}',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                toast: true,
                position: 'top-end',
                customClass: {
                    popup: 'colored-toast'
                }
            });
        </script>
    @endif

    @if ($errors->any())
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Validation Error!',
                html: '<div class="text-center space-y-2">@foreach ($errors->all() as $error)<p class="text-base">{{ $error }}</p>@endforeach</div>',
                showConfirmButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'OK',
                width: '500px',
                customClass: {
                    htmlContainer: 'text-base'
                }
            });
        </script>
    @endif

    <script>
        // Inisialisasi sidebar state
        let sidebarOpen = window.innerWidth >= 1024;

        // Set initial state
        document.addEventListener('DOMContentLoaded', function() {
            updateSidebarState();

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    sidebarOpen = true;
                } else {
                    sidebarOpen = false;
                }
                updateSidebarState();
            });
        });

        // Toggle sidebar
        function toggleSidebar() {
            sidebarOpen = !sidebarOpen;
            updateSidebarState();
        }

        // Update sidebar state
        function updateSidebarState() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');

            if (sidebarOpen) {
                sidebar.classList.remove('closed');
                sidebar.classList.add('open');
                // Overlay hanya muncul di mobile (< 1024px)
                if (window.innerWidth < 1024) {
                    overlay.classList.remove('hide');
                    overlay.classList.add('show');
                } else {
                    overlay.classList.remove('show');
                    overlay.classList.add('hide');
                }
            } else {
                sidebar.classList.remove('open');
                sidebar.classList.add('closed');
                overlay.classList.remove('show');
                overlay.classList.add('hide');
            }
        }

        // Toggle dropdown
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('dropdown');
            const button = event.target.closest('button[onclick="toggleDropdown()"]');

            if (!button && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>

</html>
