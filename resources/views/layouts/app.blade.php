<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1">
  <meta name="csrf-token"
        content="{{ csrf_token() }}">
  <title>{{ $title }}</title>
  <link rel="preconnect"
        href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap"
        rel="stylesheet" />
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

    .menu-item {
      transition: all 0.2s ease;
      padding: 10px 12px;
      border-radius: 8px;
    }
    
    .menu-item svg {
      color: #6B7280;
    }
    
    .menu-item p {
      color: #374151;
    }

    .menu-item:hover {
      background-color: #F3F4F6;
    }

    .menu-item.active {
      background-color: #EFF6FF;
      border-radius: 8px;
    }

    .menu-item.active svg {
      color: #2563EB;
    }

    .menu-item.active p {
      color: #1D4ED8;
      font-weight: 600;
    }

    .menu-item.active:hover {
      background-color: #DBEAFE;
    }
  </style>
</head>

<body class="font-sans antialiased bg-gray-50">
  <div class="flex h-screen bg-gray-50">
    <div id="overlay"
         class="overlay hide fixed inset-0 bg-[rgba(0,0,0,0.4)] z-20 lg:hidden"
         onclick="toggleSidebar()"></div>

    <aside id="sidebar"
           class="sidebar bg-[#FAFAFA] border-r border-gray-200 fixed lg:relative inset-y-0 left-0 z-30 overflow-y-auto">
      <div class="flex items-center gap-3 p-6 border-b border-gray-200">
        <div class="flex items-center justify-center w-11 h-11 rounded-[14px] bg-[linear-gradient(135deg,#155DFC_0%,#4F39F6_100%)] shadow-[0_10px_15px_-3px_rgba(0,0,0,0.10),0_4px_6px_-4px_rgba(0,0,0,0.10)] flex-shrink-0">
          <svg xmlns="http://www.w3.org/2000/svg"
               fill="none"
               viewBox="0 0 24 24"
               stroke-width="1.5"
               stroke="currentColor"
               class="size-6 text-white">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
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
            <p class="text-[12px] text-gray-500 font-bold tracking-wider leading-4 sidebar-section-title mb-1">MAIN MENU</p>
            <div class="flex flex-col gap-1">
              <a href="{{ route('modules.index') }}"
                 class="menu-item sidebar-link flex items-center gap-3 {{ request()->routeIs('modules.*') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-5 flex-shrink-0">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v6.75m0 0l-3-3m3 3l3-3m-8.25 6a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                </svg>
                <p class="text-[14px] sidebar-text font-medium">Capture Data</p>
              </a>
              <a href="{{ route('migrate.index') }}"
                 class="menu-item sidebar-link flex items-center gap-3 {{ request()->routeIs('migrate.*') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-5 flex-shrink-0">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                </svg>
                <p class="text-[14px] sidebar-text font-medium">Migrate Data</p>
              </a>
              <a href="{{ route('transaction-number-mappings.index') }}"
                 class="menu-item sidebar-link flex items-center gap-3 {{ request()->routeIs('transaction-number-mappings.*') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-5 flex-shrink-0">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                </svg>
                <p class="text-[14px] sidebar-text font-medium">Number Mappings</p>
              </a>
              <a href="{{ route('system-logs.index') }}"
                 class="menu-item sidebar-link flex items-center gap-3 {{ request()->routeIs('system-logs.*') && !request()->routeIs('system-logs.queue') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-5 flex-shrink-0">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184M12 10.5h.008v.008H12v-.008zm0 3h.008v.008H12v-.008zm0 3h.008v.008H12v-.008zM10.5 10.5h.008v.008H10.5v-.008zm0 3h.008v.008H10.5v-.008zm0 3h.008v.008H10.5v-.008z" />
                </svg>
                <p class="text-[14px] sidebar-text font-medium">Activity Logs</p>
              </a>
              <a href="{{ route('system-logs.queue') }}"
                 class="menu-item sidebar-link flex items-center gap-3 {{ request()->routeIs('system-logs.queue') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-5 flex-shrink-0">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z" />
                </svg>
                <p class="text-[14px] sidebar-text font-medium">Background Queue</p>
              </a>
            </div>
          </div>
          <div class="flex flex-col gap-3">
            <p class="text-[12px] text-gray-500 font-bold tracking-wider leading-4 sidebar-section-title mb-1">SETTINGS</p>
            <div class="flex flex-col gap-1">
              @can('manage_settings')
                <a href="{{ route('configuration.index') }}"
                   class="menu-item sidebar-link flex items-center gap-3 {{ request()->routeIs('configuration.*') ? 'active' : '' }}">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-5 flex-shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                  <p class="text-[14px] sidebar-text font-medium">App Settings</p>
                </a>
              @endcan
              @can('manage_users')
                <a href="{{ route('users.index') }}"
                   class="menu-item sidebar-link flex items-center gap-3 {{ request()->routeIs('users.*') ? 'active' : '' }}">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-5 flex-shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                  </svg>
                  <p class="text-[14px] sidebar-text font-medium">User Management</p>
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
            <button onclick="toggleSidebar()"
                    class="text-gray-500 focus:outline-none flex-shrink-0 mr-5">
              <svg xmlns="http://www.w3.org/2000/svg"
                   fill="none"
                   viewBox="0 0 24 24"
                   stroke-width="1.5"
                   stroke="currentColor"
                   class="size-6 cursor-pointer">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
              </svg>
            </button>
            {{ $header }}
          </div>
          <div class="flex items-center space-x-3 sm:space-x-5 flex-shrink-0">
            @if(session('database_name'))
              <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-blue-50/80 border border-blue-100 rounded-full" title="Currently Active Database">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                </span>
                <span class="text-xs font-semibold text-blue-700">{{ session('database_name') }}</span>
              </div>
            @endif

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
                <form method="POST"
                      action="{{ route('logout') }}">
                  @csrf
                  <button type="submit"
                          class="block w-full text-left px-4 py-2">
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
  <div x-data="{ openOptions: false }" class="fixed bottom-6 right-6 z-50">
      <div x-show="openOptions" @click.away="openOptions = false" x-transition.opacity.duration.200ms class="absolute bottom-16 right-0 mb-2 w-56 bg-white rounded-xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] border border-gray-100 overflow-hidden" style="display: none;">
          <div class="py-2">
              <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50/50">Maintenance</div>
              <button onclick="clearAllTransactions()" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3 transition-colors">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                  Clear All Transactions
              </button>
              <button onclick="clearAllLogs()" class="w-full text-left px-4 py-3 text-sm text-orange-600 hover:bg-orange-50 flex items-center gap-3 transition-colors border-t border-gray-50">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                  Clear System Logs
              </button>
          </div>
      </div>
      <button @click="openOptions = !openOptions" class="flex items-center justify-center w-14 h-14 bg-gray-900 text-white rounded-full shadow-lg hover:bg-gray-800 hover:scale-105 active:scale-95 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854-.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
      </button>
  </div>
  <x-global-job-monitor />

  <script>
    function clearAllTransactions() {
        Swal.fire({
            title: "Clear All Transactions?",
            text: "Are you sure you want to delete all transaction records permanently? This cannot be undone.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#ef4444",
            cancelButtonColor: "#6b7280",
            confirmButtonText: "Yes, clear data",
            customClass: {
                popup: "rounded-xl shadow-lg border border-gray-100",
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("/migrate/clear-transactions", {
                    method: "POST",
                    headers: {
                        "Accept": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document.querySelector("meta[name=\'csrf-token\']").content
                    }
                })
                .then(res => res.json())
                .then(data => {
                    Swal.fire("Deleted!", data.message || "All transactions cleared.", "success")
                    .then(() => window.location.reload());
                });
            }
        });
    }

    function clearAllLogs() {
        Swal.fire({
            title: "Clear System Logs?",
            text: "Are you sure you want to delete all system logs and job queues? This cannot be undone.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#ef4444",
            cancelButtonColor: "#6b7280",
            confirmButtonText: "Yes, clear logs",
            customClass: {
                popup: "rounded-xl shadow-lg border border-gray-100",
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("/system-logs/clear", {
                    method: "POST",
                    headers: {
                        "Accept": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document.querySelector("meta[name=\'csrf-token\']").content
                    }
                })
                .then(res => res.json())
                .then(data => {
                    Swal.fire("Deleted!", data.message || "All system logs cleared.", "success")
                    .then(() => window.location.reload());
                });
            }
        });
    }
  </script>
</body>
</html>
