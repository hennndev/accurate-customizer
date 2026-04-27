<x-app-layout>
  <x-slot name="title">Queue List</x-slot>
  <x-slot name="header">
    <div class="flex items-center gap-3">
      <div class="flex items-center justify-center w-9 h-9 rounded-[14px] bg-[linear-gradient(135deg,#155DFC_0%,#4F39F6_100%)]">
        <svg xmlns="http://www.w3.org/2000/svg"
             fill="none"
             viewBox="0 0 24 24"
             stroke-width="1.5"
             stroke="currentColor"
             class="size-5 text-white">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                d="M3.75 5.25h16.5m-16.5 6h16.5m-16.5 6h16.5" />
        </svg>
      </div>
      <div>
        <p class="text-[16px] font-medium text-black">Queue List</p>
        <p class="text-sm font-medium text-gray-600">Semua antrian aktif dan riwayat status queue</p>
      </div>
    </div>
  </x-slot>

  <div class="space-y-4"
       x-data="{ selected: [], selectAll: false, showDeleteModal: false }">
    <form method="GET"
          action="{{ route('system-logs.queue') }}"
          class="flex flex-col md:flex-row gap-3">
      <select name="queue_status"
              class="border rounded-lg px-3 py-2">
        <option value="all"
                @selected(request('queue_status', 'all') === 'all')>Semua Status</option>
        <option value="queued"
                @selected(request('queue_status') === 'queued')>Queued</option>
        <option value="running"
                @selected(request('queue_status') === 'running')>Running</option>
        <option value="success"
                @selected(request('queue_status') === 'success')>Success</option>
        <option value="warning"
                @selected(request('queue_status') === 'warning')>Warning</option>
        <option value="failed"
                @selected(request('queue_status') === 'failed')>Failed</option>
        <option value="info"
                @selected(request('queue_status') === 'info')>Info</option>
      </select>
      <button class="px-4 py-2 rounded-lg bg-blue-600 text-white">Filter</button>
    </form>

    <form id="queueBulkDeleteForm"
          method="POST"
          action="{{ route('system-logs.queue.destroyMultiple') }}"
          class="space-y-3"
          @submit.prevent="showDeleteModal = true">
      @csrf
      @method('DELETE')
      <template x-for="id in selected"
                :key="id">
        <input type="hidden"
               name="ids[]"
               :value="id">
      </template>
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox"
                 x-model="selectAll"
                 @change="selected = selectAll ? @js($logs->pluck('id')->all()) : []" />
          Select All
        </label>
        <button type="submit"
                class="px-4 py-2 rounded-lg bg-red-600 text-white"
                :disabled="selected.length === 0">Delete Selected</button>
      </div>

      <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="text-left p-3"></th>
              <th class="text-left p-3">Waktu</th>
              <th class="text-left p-3">Type</th>
              <th class="text-left p-3">Module</th>
              <th class="text-left p-3">Status</th>
              <th class="text-left p-3">Message</th>
            </tr>
          </thead>
          <tbody>
            @forelse($logs as $log)
              <tr class="border-t">
                <td class="p-3">
                  <input type="checkbox"
                         value="{{ $log->id }}"
                         x-model="selected" />
                </td>
                <td class="p-3">{{ $log->created_at }}</td>
                <td class="p-3">{{ $log->event_type }}</td>
                <td class="p-3">{{ $log->module }}</td>
                <td class="p-3">{{ $log->status }}</td>
                <td class="p-3">{{ $log->message }}</td>
              </tr>
            @empty
              <tr>
                <td class="p-3"
                    colspan="5">Tidak ada data.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </form>

    <div x-show="showDeleteModal"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl p-5 w-full max-w-md shadow-lg">
        <p class="text-lg font-semibold text-black">Konfirmasi Delete</p>
        <p class="text-sm text-gray-600 mt-2">Hapus <span x-text="selected.length"></span> queue terpilih?</p>
        <div class="mt-4 flex justify-end gap-2">
          <button type="button"
                  class="px-4 py-2 rounded-lg border"
                  @click="showDeleteModal = false">Batal</button>
          <button type="button"
                  class="px-4 py-2 rounded-lg bg-red-600 text-white"
                  @click="$nextTick(() => document.getElementById('queueBulkDeleteForm').submit())">Delete</button>
        </div>
      </div>
    </div>
  </div>
</x-app-layout>
