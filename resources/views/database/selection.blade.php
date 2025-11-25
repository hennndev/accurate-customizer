<x-guest-layout>
    {{-- DITAMBAHKAN: Alpine.js untuk mengontrol animasi saat halaman dimuat --}}
    <div 
        class="w-full sm:max-w-md mt-6 px-6 py-8 bg-white shadow-xl overflow-hidden sm:rounded-2xl transform transition-all duration-500 ease-out"
        x-data="{ loaded: false }"
        x-init="setTimeout(() => loaded = true, 100)"
        :class="{ 'opacity-100 translate-y-0': loaded, 'opacity-0 translate-y-4': !loaded }"
    >
        
        {{-- DITAMBAHKAN: Ikon visual untuk mempercantik tampilan --}}
        <div class="flex justify-center mb-6">
            <div class="p-3 bg-red-100 rounded-full">
                <svg class="w-8 h-8 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" opacity="0.4" transform="translate(-2, -2)"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" opacity="0.2" transform="translate(-4, -4)"/>
                </svg>
            </div>
        </div>

        <h2 class="text-center text-2xl font-bold text-gray-800 mb-2">Pilih Database Accurate</h2>
        <p class="text-center text-gray-500 text-sm mb-8">Pilih database untuk melanjutkan sesi Anda.</p>

        @if (session('error'))
            <div class="mb-4 p-4 text-sm rounded-lg bg-red-100 text-red-700">
                {{ session('error') }}
            </div>
        @endif
        
        <form method="POST" action="{{ route('database.select') }}">
            @csrf
            <div>
                <label for="database_selection" class="block mb-2 text-sm font-medium text-gray-700">Database Tersedia</label>
                {{-- DIUBAH: Diberi class 'transition' untuk efek focus yang halus --}}
                <select id="database_selection" name="selected_db_json" 
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5 transition duration-200">
                    
                    <option selected disabled>-- Pilih salah satu --</option>
                    
                    @if(isset($databases))
                        @foreach ($databases as $db)
                            <option value="{{ json_encode($db) }}">{{ $db['alias'] }}</option>
                        @endforeach
                    @endif
                </select>
            </div>

            <div class="mt-8">
                {{-- DIUBAH: Tombol diberi efek hover dan active yang lebih interaktif --}}
                <button type="submit" 
                        class="w-full px-4 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all duration-200 transform hover:-translate-y-1 active:scale-95">
                    Login
                </button>
            </div>
        </form>
    </div>
</x-guest-layout>