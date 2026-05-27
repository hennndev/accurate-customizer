<x-app-layout>
  <x-slot name="title">Transaction Number Mapping</x-slot>
  <x-slot name="header">
    <div class="flex items-center gap-3">
      <div class="flex items-center justify-center w-9 h-9 rounded-[14px] bg-[linear-gradient(135deg,#155DFC_0%,#4F39F6_100%)] shadow-[0_10px_15px_-3px_rgba(0,0,0,0.10),0_4px_6px_-4px_rgba(0,0,0,0.10)] flex-shrink-0">
        <svg xmlns="http://www.w3.org/2000/svg"
             fill="none"
             viewBox="0 0 24 24"
             stroke-width="1.5"
             stroke="currentColor"
             class="size-5 text-white">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h12m-12 0v3.75m0-3.75h12m0 0a2.25 2.25 0 0 0 2.25-2.25V3m-2.25 13.5v3.75M8.25 6h7.5m-7.5 3h7.5m-7.5 3h4.5" />
        </svg>
      </div>
      <div class="flex flex-col gap-1">
        <p class="text-[16px] font-medium text-black">Transaction Number Mapping</p>
        <p class="text-sm font-medium text-gray-600">Capture dan kelola list mapping nomor transaksi</p>
      </div>
    </div>
  </x-slot>

  <div class="py-6"
       x-data="{
           capturing: false,
           monitorId: null,
           monitorVisible: false,
           monitorStatus: 'idle',
           monitorMessage: '',
           progress: 0,
           savedCount: 0,
           skippedCount: 0,
           failedCount: 0,
           processedPages: 0,
           processedItems: 0,
           async restoreActiveMonitor() {
               try {
                   const response = await fetch('/system-logs/active?event_type=transaction_number_mapping_queue', {
                       method: 'GET',
                       credentials: 'same-origin',
                       headers: {
                           'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest',
                       },
                   });
       
                   if (!response.ok) {
                       return;
                   }
       
                   const result = await response.json();
                   if (!(result?.success && result?.active && result?.log?.id)) {
                       return;
                   }
       
                   this.monitorId = result.log.id;
                   this.monitorVisible = true;
                   this.capturing = true;
                   this.monitorStatus = result.log.status || 'running';
                   this.monitorMessage = result.log.message || 'Capture sedang berjalan...';
                   this.syncPayload(result.log.payload || {});
                   this.pollStatus();
               } catch (error) {
                   console.error(error);
               }
           },
           syncPayload(payload) {
               this.progress = Number(payload?.progress || 0);
               this.savedCount = Number(payload?.saved_count || 0);
               this.skippedCount = Number(payload?.skipped_count || 0);
               this.failedCount = Number(payload?.failed_count || 0);
               this.processedPages = Number(payload?.processed_pages || 0);
               this.processedItems = Number(payload?.processed_items || 0);
           },
           async captureData() {
               const module = document.getElementById('capture_module').value;
               const captureDatabaseId = document.getElementById('capture_database_id')?.value || '';
               const startDate = document.getElementById('start_date')?.value || '';
               const endDate = document.getElementById('end_date')?.value || '';
               const startTime = document.getElementById('start_time')?.value || '00:00';
               const endTime = document.getElementById('end_time')?.value || '23:59';
               const filterType = document.querySelector('input[name=\'filter_type\']:checked')?.value || 'range';
       
               this.capturing = true;
               this.monitorVisible = true;
               this.monitorStatus = 'queued';
               this.monitorMessage = 'Menyiapkan capture list...';
               this.progress = 1;
               this.savedCount = 0;
               this.skippedCount = 0;
               this.failedCount = 0;
               this.processedPages = 0;
               this.processedItems = 0;
       
               try {
                   const response = await fetch('{{ route('transaction-number-mappings.capture') }}', {
                       method: 'POST',
                       credentials: 'same-origin',
                       headers: {
                           'Content-Type': 'application/json',
                           'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest',
                           'X-CSRF-TOKEN': '{{ csrf_token() }}',
                       },
                       body: JSON.stringify({
                           module,
                           capture_database_id: captureDatabaseId,
                           start_date: startDate,
                           end_date: endDate,
                           start_time: startTime,
                           end_time: endTime,
                           filter_type: filterType,
                       }),
                   });
       
                   const result = await response.json();
                   if (!(response.ok && result?.success && result?.monitor_id)) {
                       throw new Error(result?.message || 'Gagal queue capture');
                   }
       
                   this.monitorId = result.monitor_id;
                   this.monitorStatus = 'running';
                   this.monitorMessage = 'Capture sedang diproses...';
                   await this.pollStatus();
                   window.location.reload();
               } catch (error) {
                   this.capturing = false;
                   this.monitorStatus = 'failed';
                   this.monitorMessage = error?.message || 'Capture gagal';
               }
           },
           async pollStatus() {
               if (!this.monitorId) {
                   this.capturing = false;
                   return;
               }
       
               while (true) {
                   await new Promise(resolve => setTimeout(resolve, 1500));
       
                   const response = await fetch(`/system-logs/${this.monitorId}/status`, {
                       method: 'GET',
                       credentials: 'same-origin',
                       headers: {
                           'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest',
                       },
                   });
       
                   if (!response.ok) {
                       continue;
                   }
       
                   const result = await response.json();
                   this.monitorStatus = result?.status || this.monitorStatus;
                   this.monitorMessage = result?.message || this.monitorMessage;
                   this.syncPayload(result?.payload || {});
       
                   if (['success', 'warning', 'info', 'failed'].includes(this.monitorStatus)) {
                       this.progress = 100;
                       this.capturing = false;
                       break;
                   }
               }
           },
           async cancelCapture() {
               if (!this.monitorId) {
                   return;
               }
       
               try {
                   const response = await fetch(`/system-logs/${this.monitorId}/cancel`, {
                       method: 'POST',
                       credentials: 'same-origin',
                       headers: {
                           'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest',
                           'X-CSRF-TOKEN': '{{ csrf_token() }}',
                       },
                   });
       
                   const result = await response.json();
                   this.capturing = false;
                   this.monitorStatus = result?.success ? 'failed' : this.monitorStatus;
                   this.monitorMessage = result?.message || 'Capture dibatalkan';
               } catch (error) {
                   console.error(error);
               }
           },
       }"
       x-init="restoreActiveMonitor()">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col gap-6">
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <form method="GET"
              action="{{ route('transaction-number-mappings.index') }}"
              class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm text-gray-700 mb-1">Module</label>
            <select name="module"
                    class="w-full border-gray-300 rounded-lg text-sm">
              @foreach ($moduleOptions as $slug => $label)
                <option value="{{ $slug }}"
                        @selected(request('module', 'sales-invoice') === $slug)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-700 mb-1">Database</label>
            <select name="db_name"
                    class="w-full border-gray-300 rounded-lg text-sm">
              <option value="">All Database</option>
              @foreach ($dbOptions as $dbName)
                <option value="{{ $dbName }}"
                        @selected(request('db_name') === $dbName)>{{ $dbName }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-700 mb-1">Search Number</label>
            <input type="text"
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="Old/New Number"
                   class="w-full border-gray-300 rounded-lg text-sm" />
          </div>
          <div class="flex items-end gap-2">
            <button type="submit"
                    class="px-4 py-2 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded-lg">Filter</button>
            <a href="{{ route('transaction-number-mappings.index') }}"
               class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Reset</a>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-5 flex flex-col gap-4">
        <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
          <div>
            <label class="block text-sm text-gray-700 mb-1">Database Capture</label>
            <select id="capture_database_id"
                    class="w-full border-gray-300 rounded-lg text-sm"
                    :disabled="capturing">
              @foreach ($captureDatabaseOptions as $captureDb)
                <option value="{{ $captureDb['id'] }}"
                        @selected((int) $selectedCaptureDatabaseId === (int) $captureDb['id'])>{{ $captureDb['name'] }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="block text-sm text-gray-700 mb-1">Module Capture</label>
            <select id="capture_module"
                    class="w-full border-gray-300 rounded-lg text-sm"
                    :disabled="capturing">
              @foreach ($moduleOptions as $slug => $label)
                <option value="{{ $slug }}"
                        @selected($selectedModule === $slug)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="block text-sm text-gray-700 mb-1">Start Date</label>
            <input id="start_date"
                   type="date"
                   class="w-full border-gray-300 rounded-lg text-sm"
                   :disabled="capturing" />
          </div>

          <div>
            <label class="block text-sm text-gray-700 mb-1">End Date</label>
            <input id="end_date"
                   type="date"
                   class="w-full border-gray-300 rounded-lg text-sm"
                   :disabled="capturing" />
          </div>

          <div>
            <label class="block text-sm text-gray-700 mb-1">Start Time</label>
            <input id="start_time"
                   type="time"
                   value="00:00"
                   class="w-full border-gray-300 rounded-lg text-sm"
                   :disabled="capturing" />
          </div>

          <div>
            <label class="block text-sm text-gray-700 mb-1">End Time</label>
            <input id="end_time"
                   type="time"
                   value="23:59"
                   class="w-full border-gray-300 rounded-lg text-sm"
                   :disabled="capturing" />
          </div>

          <div class="flex items-end gap-2">
            <button type="button"
                    @click="captureData()"
                    :disabled="capturing"
                    class="px-4 py-2 text-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 rounded-lg">Capture List</button>
            <button type="button"
                    @click="cancelCapture()"
                    x-show="capturing"
                    class="px-4 py-2 text-sm text-white bg-rose-600 hover:bg-rose-700 rounded-lg">Cancel</button>
          </div>
        </div>

        <div class="flex flex-wrap gap-4 text-sm text-gray-700">
          <label class="inline-flex items-center gap-2">
            <input type="radio"
                   name="filter_type"
                   value="range"
                   checked
                   :disabled="capturing" />
            <span>Range Trans Date</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input type="radio"
                   name="filter_type"
                   value="equal"
                   :disabled="capturing" />
            <span>Equal Trans Date</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input type="radio"
                   name="filter_type"
                   value="last_update"
                   :disabled="capturing" />
            <span>Range Last Update</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input type="radio"
                   name="filter_type"
                   value="last_update_equal"
                   :disabled="capturing" />
            <span>Equal Last Update</span>
          </label>
        </div>

        <div x-show="monitorVisible"
             x-cloak
             class="border border-gray-200 rounded-lg p-4 bg-gray-50 flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-gray-900">Status Capture</p>
            <span class="text-xs px-2 py-1 rounded-full"
                  :class="monitorStatus === 'failed' ? 'bg-rose-100 text-rose-700' : (monitorStatus === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700')"
                  x-text="monitorStatus"></span>
          </div>
          <p class="text-sm text-gray-600"
             x-text="monitorMessage"></p>
          <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
            <div class="h-2 bg-blue-600"
                 :style="`width: ${progress}%`"></div>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-xs text-gray-600">
            <p>Progress: <span x-text="progress"></span>%</p>
            <p>Saved: <span x-text="savedCount"></span></p>
            <p>Skipped: <span x-text="skippedCount"></span></p>
            <p>Failed: <span x-text="failedCount"></span></p>
            <p>Pages: <span x-text="processedPages"></span> | Items: <span x-text="processedItems"></span></p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
              <tr>
                <th class="text-left px-4 py-3 font-medium text-gray-700">Database</th>
                <th class="text-left px-4 py-3 font-medium text-gray-700">Module</th>
                <th class="text-left px-4 py-3 font-medium text-gray-700">Old Number</th>
                <th class="text-left px-4 py-3 font-medium text-gray-700">New Number</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($mappings as $mapping)
                <tr class="border-b border-gray-100">
                  <td class="px-4 py-3 text-gray-700">{{ $mapping->db_name }}</td>
                  <td class="px-4 py-3 text-gray-700">{{ $moduleOptions[$selectedModule] ?? 'Sales Invoice' }}</td>
                  <td class="px-4 py-3 font-medium text-gray-900">{{ $mapping->old_number }}</td>
                  <td class="px-4 py-3 text-gray-700">{{ $mapping->new_number ?: '-' }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="4"
                      class="px-4 py-8 text-center text-gray-500">Belum ada data mapping number.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="p-4 border-t border-gray-200">
          {{ $mappings->links() }}
        </div>
      </div>
    </div>
  </div>
</x-app-layout>
