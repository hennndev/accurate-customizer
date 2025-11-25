<x-app-layout>
   <x-slot name="header">
        <div class="flex justify-between items-center w-full">
            <div class="flex items-center space-x-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Profile') }}
                </h2>
            </div>
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <button class="flex items-center space-x-2 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 focus:outline-none">
                        <span>{{ Auth::user()->name }}</span>
                </div>
                <button class="relative text-gray-600 hover:text-gray-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.405L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">1</span>
                </button>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none transition duration-150 ease-in-out">
                            <div class="h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center">
                                <span class="text-sm font-bold text-white">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </span>
                            </div>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="block px-4 py-2 text-xs text-gray-400">{{ __('Manage Account') }}</div>
                        <x-dropdown-link href="{{ route('profile.edit') }}">{{ __('Profile') }}</x-dropdown-link>
                        <div class="border-t border-gray-200"></div>
                        <form method="POST" action="{{ route('logout') }}" x-data>
                            @csrf
                            <x-dropdown-link href="{{ route('logout') }}" @click.prevent="$root.submit();">{{ __('Log Out') }}</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </x-slot>
    {{-- Breadcrumb untuk Halaman Inventory --}}
    <x-slot name="breadcrumb">
        <div class="flex items-center">
            <svg class="flex-shrink-0 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
            <span class="ml-2 text-sm font-medium text-gray-500">Settings</span>
            <svg class="flex-shrink-0 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
            <span class="ml-2 text-sm font-medium text-gray-500">Profile</span>
        </div>
    </x-slot>
    <div
        {{-- BARU: Wrapper untuk animasi fade-in seluruh halaman --}}
        x-data="{ show: false }"
        x-init="setTimeout(() => show = true, 50)"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-4"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        class="py-12"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- DIUBAH: Header halaman dibuat konsisten dengan halaman lain --}}
            <div>
                <h2 class="text-3xl font-bold tracking-tight text-gray-900">Profile</h2>
                <p class="mt-1 text-base text-gray-500">Kelola informasi profil dan keamanan akun Anda.</p>
            </div>

            {{-- DIUBAH: Seluruh konten dibungkus dalam satu kartu modern --}}
            <div class="bg-white overflow-hidden rounded-xl shadow-sm">
                {{-- BARU: Header kartu yang menggabungkan judul dan tombol aksi --}}
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">
                                Informasi Profil
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Informasi dasar akun Anda yang tercatat di sistem.
                            </p>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="inline-flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200 transform hover:-translate-y-px">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.5L14.732 3.732z"></path></svg>
                            Edit Profil
                        </a>
                    </div>
                </div>

                {{-- Detail Informasi Pengguna --}}
                <div class="p-6">
                    <dl 
                        class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-2"
                        {{-- BARU: Alpine.js untuk animasi staggered pada item --}}
                        x-data
                        x-init="
                            $el.querySelectorAll('.animated-item').forEach((item, index) => {
                                item.style.opacity = 0;
                                item.style.transform = 'translateY(10px)';
                                setTimeout(() => {
                                    item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                                    item.style.opacity = 1;
                                    item.style.transform = 'translateY(0)';
                                }, 75 * index);
                            })
                        "
                    >
                        {{-- DIUBAH: Setiap item sekarang memiliki ikon --}}
                        <div class="flex items-start animated-item">
                            <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                            <div class="ml-4">
                                <dt class="text-sm font-medium text-gray-500">Nama Lengkap</dt>
                                <dd class="mt-1 text-base font-semibold text-gray-800">{{ $user->name }}</dd>
                            </div>
                        </div>

                        <div class="flex items-start animated-item">
                            <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <div class="ml-4">
                                <dt class="text-sm font-medium text-gray-500">Alamat Email</dt>
                                <dd class="mt-1 text-base font-semibold text-gray-800">{{ $user->email }}</dd>
                            </div>
                        </div>

                        <div class="flex items-start animated-item">
                           <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12 12 0 0012 21.944a12 12 0 008.618-3.04A11.955 11.955 0 0112 2.944z"></path></svg>
                            </div>
                            <div class="ml-4">
                                <dt class="text-sm font-medium text-gray-500">Peran (Role)</dt>
                                <dd class="mt-1 text-base font-semibold text-gray-800">{{ ucfirst($user->role) }}</dd>
                            </div>
                        </div>

                        <div class="flex items-start animated-item">
                            <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            <div class="ml-4">
                                <dt class="text-sm font-medium text-gray-500">Akun Dibuat</dt>
                                <dd class="mt-1 text-base font-semibold text-gray-800">{{ $user->created_at->format('d F Y') }}</dd>
                            </div>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>