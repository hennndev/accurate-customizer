<x-app-layout>
  <x-slot name="title">Daftar Antrean</x-slot>
  <x-slot name="header">
    <div class="flex items-center gap-3">
      <div class="flex items-center justify-center w-9 h-9 rounded-[14px] bg-[linear-gradient(135deg,#155DFC_0%,#4F39F6_100%)] shadow-[0_10px_15px_-3px_rgba(0,0,0,0.10),0_4px_6px_-4px_rgba(0,0,0,0.10)] flex-shrink-0">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-white">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5m-16.5 6h16.5m-16.5 6h16.5" />
        </svg>
      </div>
      <div class="flex flex-col gap-1">
        <p class="text-[16px] font-medium text-black">Daftar Antrean</p>
        <p class="text-sm font-medium text-gray-600">Semua antrean aktif dan riwayat status queue</p>
      </div>
    </div>
  </x-slot>

  <div class="flex flex-col gap-5 md:gap-7" x-data="{ selected: [], selectAll: false, showDeleteModal: false }">
    
    @if (session('success'))
      <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 flex items-center gap-2 shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
        </svg>
        {{ session('success') }}
      </div>
    @endif
    @if (session('error'))
      <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 flex items-center gap-2 shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
        </svg>
        {{ session('error') }}
      </div>
    @endif

    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
      <form method="GET" action="{{ route('system-logs.queue') }}" class="w-full md:w-auto flex flex-col sm:flex-row items-center gap-3">
        <div class="relative w-full sm:w-[200px]">
          <select name="queue_status"
                  @change="$event.target.form.submit()"
                  class="w-full appearance-none bg-white border border-gray-200 text-gray-700 text-sm rounded-xl py-2.5 px-4 pr-10 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-medium shadow-sm transition cursor-pointer">
            <option value="all" @selected(request('queue_status', 'all') === 'all')>Semua Status</option>
            <option value="queued" @selected(request('queue_status') === 'queued')>Antre</option>
            <option value="running" @selected(request('queue_status') === 'running')>Berjalan</option>
            <option value="success" @selected(request('queue_status') === 'success')>Sukses</option>
            <option value="warning" @selected(request('queue_status') === 'warning')>Peringatan</option>
            <option value="failed" @selected(request('queue_status') === 'failed')>Gagal</option>
            <option value="info" @selected(request('queue_status') === 'info')>Info</option>
          </select>
          <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
        </div>
      </form>
    </div>

    <form id="queueBulkDeleteForm" method="POST" action="{{ route('system-logs.queue.destroyMultiple') }}" class="flex flex-col gap-4" @submit.prevent="showDeleteModal = true">
      @csrf
      @method('DELETE')
      <template x-for="id in selected" :key="id">
        <input type="hidden" name="ids[]" :value="id">
      </template>

      <!-- Action Bar -->
      <div class="flex items-center justify-between bg-white px-5 py-3 rounded-xl border border-gray-200 shadow-sm transition-all duration-300"
           :class="selected.length > 0 ? 'ring-2 ring-blue-500 bg-blue-50/30' : ''">
        <label class="flex items-center gap-3 cursor-pointer group">
          <div class="relative flex items-center">
            <input type="checkbox" x-model="selectAll" @change="selected = selectAll ? @js($logs->pluck('id')->all()) : []" class="peer h-5 w-5 cursor-pointer appearance-none rounded border-2 border-gray-300 bg-white checked:border-blue-600 checked:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 transition-all" />
            <svg class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-3.5 h-3.5 pointer-events-none opacity-0 peer-checked:opacity-100 text-white stroke-white stroke-[3] transition-opacity duration-200" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"></path>
            </svg>
          </div>
          <span class="text-sm font-semibold text-gray-700 group-hover:text-black transition">Pilih Semua</span>
        </label>
        
        <button type="submit" 
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                :class="selected.length > 0 ? 'bg-red-600 hover:bg-red-700 text-white hover:shadow-md' : 'bg-gray-100 text-gray-400'"
                :disabled="selected.length === 0">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
          </svg>
          <span x-text="selected.length > 0 ? `Hapus / Hentikan (${selected.length})` : 'Hapus / Hentikan Terpilih'"></span>
        </button>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-500 font-medium">
              <tr>
                <th class="p-4 w-12 text-center">#</th>
                <th class="p-4 font-semibold whitespace-nowrap">Waktu</th>
                <th class="p-4 font-semibold whitespace-nowrap">Tipe</th>
                <th class="p-4 font-semibold whitespace-nowrap">Modul</th>
                <th class="p-4 font-semibold whitespace-nowrap">Status</th>
                <th class="p-4 font-semibold whitespace-nowrap">Pesan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              @forelse($logs as $log)
                <tr class="hover:bg-blue-50/30 transition-colors group">
                  <td class="p-4 text-center">
                    <div class="relative flex items-center justify-center">
                      <input type="checkbox" value="{{ $log->id }}" x-model="selected" class="peer h-5 w-5 cursor-pointer appearance-none rounded border-2 border-gray-300 bg-white checked:border-blue-600 checked:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all" />
                      <svg class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-3.5 h-3.5 pointer-events-none opacity-0 peer-checked:opacity-100 text-white stroke-white stroke-[3] transition-opacity duration-200" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"></path></svg>
                    </div>
                  </td>
                  <td class="p-4 text-gray-600 whitespace-nowrap">{{ $log->created_at->format('d M Y, H:i') }}</td>
                  <td class="p-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold
                      {{ $log->event_type === 'delete' || $log->event_type === 'mass delete' ? 'bg-red-50 text-red-600 border border-red-100' : '' }}
                      {{ $log->event_type === 'migrate' || $log->event_type === 'migrate_queue' ? 'bg-blue-50 text-blue-600 border border-blue-100' : '' }}
                      {{ $log->event_type === 'capture' || $log->event_type === 'capture_queue' ? 'bg-violet-50 text-violet-600 border border-violet-100' : '' }}">
                      {{ [
                          'delete' => 'Hapus',
                          'mass delete' => 'Hapus Massal',
                          'migrate' => 'Migrasi',
                          'migrate_queue' => 'Antrean Migrasi',
                          'capture' => 'Capture',
                          'capture_queue' => 'Antrean Capture',
                      ][$log->event_type] ?? ucfirst($log->event_type) }}
                    </span>
                  </td>
                  <td class="p-4 text-gray-900 font-medium">{{ $log->module ?? '-' }}</td>
                  <td class="p-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border
                      {{ $log->status === 'success' ? 'bg-green-50 border-green-200 text-green-700' : '' }}
                      {{ $log->status === 'failed' ? 'bg-red-50 border-red-200 text-red-700' : '' }}
                      {{ $log->status === 'warning' || $log->status === 'partial' ? 'bg-orange-50 border-orange-200 text-orange-700' : '' }}
                      {{ $log->status === 'info' ? 'bg-blue-50 border-blue-200 text-blue-700' : '' }}
                      {{ $log->status === 'queued' ? 'bg-gray-100 border-gray-300 text-gray-700' : '' }}
                      {{ $log->status === 'running' ? 'bg-indigo-50 border-indigo-200 text-indigo-700' : '' }}">
                      @if($log->status === 'running')
                        <svg class="animate-spin -ml-0.5 mr-1.5 h-3 w-3 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                      @endif
                      {{ [
                          'success' => 'Sukses',
                          'failed' => 'Gagal',
                          'warning' => 'Peringatan',
                          'partial' => 'Sebagian',
                          'info' => 'Info',
                          'queued' => 'Antre',
                          'running' => 'Berjalan',
                      ][$log->status] ?? ucfirst($log->status) }}
                    </span>
                  </td>
                  <td class="p-4 text-gray-600 text-sm max-w-md truncate" title="{{ $log->message }}">{{ $log->message }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="p-10 text-center">
                    <div class="flex flex-col items-center justify-center">
                      <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mb-4 border border-gray-100 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-400">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                        </svg>
                      </div>
                      <p class="text-gray-900 font-semibold mb-1">Tidak Ada Antrian</p>
                      <p class="text-gray-500 text-sm">Belum ada antrian yang terdaftar dengan status ini.</p>
                    </div>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($logs instanceof \Illuminate\Pagination\LengthAwarePaginator && $logs->hasPages())
          <div class="p-4 border-t border-gray-100 bg-gray-50/50">
            {{ $logs->links() }}
          </div>
        @endif
      </div>
    </form>

    <!-- Modal Delete -->
    <div x-show="showDeleteModal" 
         style="display: none;"
         class="fixed inset-0 z-[100] overflow-y-auto" 
         aria-labelledby="modal-title" 
         role="dialog" 
         aria-modal="true">
      <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Backdrop -->
        <div x-show="showDeleteModal" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0" 
             class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" 
             @click="showDeleteModal = false" 
             aria-hidden="true"></div>

        <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

        <!-- Modal Panel -->
        <div x-show="showDeleteModal" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             class="inline-block transform overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle border border-gray-200">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg font-bold leading-6 text-gray-900" id="modal-title">Hapus Queue</h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500">Anda yakin ingin menghapus/menghentikan <strong class="text-red-600 font-bold" x-text="selected.length"></strong> queue yang dipilih? Data yang dihapus tidak dapat dikembalikan dan proses yang sedang berjalan akan dihentikan paksa.</p>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-gray-200">
            <button type="button" @click="$nextTick(() => document.getElementById('queueBulkDeleteForm').submit())" class="inline-flex w-full justify-center rounded-lg border border-transparent bg-red-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 sm:ml-3 sm:w-auto transition-colors">
              Ya, Hapus
            </button>
            <button type="button" @click="showDeleteModal = false" class="mt-3 inline-flex w-full justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto transition-colors">
              Batal
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</x-app-layout>
