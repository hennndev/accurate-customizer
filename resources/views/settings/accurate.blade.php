<x-app-layout>
  <x-slot name="header">
        Accurate Integration
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-semibold text-gray-800">Status Koneksi</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Kelola koneksi antara aplikasi ini dengan akun Accurate Online Anda.
                    </p>
                </div>

                <div class="p-6">
                    @if ($isConnected)
                        {{-- TAMPILAN JIKA SUDAH TERHUBUNG --}}
                        <div class="flex items-center p-4 bg-green-50 text-green-800 rounded-lg border border-green-200">
                            <svg class="w-6 h-6 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span class="text-sm font-medium">Aplikasi berhasil terhubung ke Accurate. Anda dapat memilih database setelah ini.</span>
                        </div>
                        <div class="mt-6 text-right">
                            <form action="{{ route('accurate.disconnect') }}" method="POST">
                                @csrf
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-5 rounded-lg shadow-md transition duration-200">
                                    Putuskan Koneksi
                                </button>
                            </form>
                        </div>
                    @else
                        {{-- TAMPILAN JIKA BELUM TERHUBUNG --}}
                        <div class="flex items-center p-4 bg-yellow-50 text-yellow-800 rounded-lg border border-yellow-200">
                            <svg class="w-6 h-6 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            <span class="text-sm font-medium">Aplikasi belum terhubung ke Accurate. Klik tombol di bawah untuk memulai.</span>
                        </div>
                        <div class="mt-6 text-right">
                            <a href="{{ route('accurate.auth') }}" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-5 rounded-lg shadow-md transition duration-200">
                                Hubungkan ke Accurate
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Menampilkan pesan sukses/error jika ada --}}
                @if (session('info'))
                    <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 text-sm text-gray-600">
                        {{ session('info') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>