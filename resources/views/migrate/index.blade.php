@php
  $total = $transactions->count();
  $pending = $transactions->where('status', 'pending')->count();
  $success = $transactions->where('status', 'success')->count();
  $failed = $transactions->where('status', 'failed')->count();
  $successRate = $total > 0 ? number_format(($success / $total) * 100, 1) : 0;
  $readyToMigrate = $pending;
@endphp

<x-app-layout>
  <x-slot name="title">Migration Data</x-slot>
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
                d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
        </svg>
      </div>
      <div class="flex flex-col gap-1">
        <p class="text-[16px] font-medium text-black">Migrate</p>
        <p class="text-sm font-medium text-gray-600">Transfer data to target database</p>
      </div>
    </div>
  </x-slot>

  <div class="flex flex-col gap-6 md:gap-8 lg:gap-10"
       x-data="{
           selectAll: false,
           selected: [],
           allTransactionIds: {{ $transactions->pluck('id')->toJson() }},
           transactionStatusById: {{ $transactions->pluck('status', 'id')->toJson() }},
           transactionModuleById: {{ $transactions->mapWithKeys(fn($t) => [$t->id => $t->module->slug ?? ''])->toJson() }},
           showDeleteModal: false,
           showSingleDeleteModal: false,
           showEditModal: false,
           deleteTarget: null,
           editTransaction: null,
           editData: '',
           editMode: 'form', // 'form' or 'json'
           parsedData: {},
           searchField: '',
           editLoading: false,
           filterModuleSelected: '{{ request('module', 'All Modules') }}',
           migrating: false,
           migrateMonitorBooting: true,
           migrateMonitorVisible: false,
           migrateMonitorId: null,
           progress: 0,
           currentStatus: 'Preparing migration...',
           showTargetDbModal: false,
           modalSelectedDbId: '',
           modalForceCreate: true,
           modalTargetIds: [],
           modalNumberingMode: 'original',
           modalPreviewData: [],
           modalTargetNumbers: {},
           modalCustomInvoiceMappings: {},
           modalPreviewLoading: false,
           showWarningModal: false,
           pendingMigrateSingleId: null,
           
           openMigrateModal(singleId = null, bypassWarning = false) {
               if (this.migrating) {
                   alert('Proses migrasi sedang berjalan. Harap tunggu hingga selesai.');
                   return;
               }
               
               if (!singleId && !this.selected.length) {
                   alert('Pilih minimal 1 transaksi untuk migrate.');
                   return;
               }

               let targetIds = singleId ? [String(singleId)] : [...new Set(this.selected.map(id => String(id)))];
               let modulesToWarn = ['purchase-invoice', 'sales-invoice', 'sales-receipt', 'purchase-payment'];
               let hasWarningModule = targetIds.some(id => modulesToWarn.includes(this.transactionModuleById[id]));

               if (hasWarningModule && !bypassWarning) {
                   this.pendingMigrateSingleId = singleId;
                   this.showWarningModal = true;
                   return;
               }

               this.showWarningModal = false;
               this.showTargetDbModal = true;
               this.modalTargetIds = targetIds;
               this.modalSelectedDbId = document.getElementById('targetDatabaseSelect')?.value || '';
               this.modalForceCreate = true;
               this.modalAddJuSuffix = false;
               this.modalNumberingMode = 'original';
               this.modalPreviewData = [];
               this.modalTargetNumbers = {};
               this.modalCustomInvoiceMappings = {};
               
               if (this.modalSelectedDbId) {
                   this.fetchPreviewData();
               }
           },
           
           async fetchPreviewData() {
               if (!this.modalTargetIds.length || !this.modalSelectedDbId) return;
               this.modalPreviewLoading = true;
               try {
                   const response = await fetch('{{ route('migrate.previewNumbers') }}', {
                       method: 'POST',
                       headers: {
                           'Content-Type': 'application/json',
                           'Accept': 'application/json',
                           'X-CSRF-TOKEN': '{{ csrf_token() }}'
                       },
                       body: JSON.stringify({
                           target_database_id: this.modalSelectedDbId,
                           ids: this.modalTargetIds
                       })
                   });
                   const data = await response.json();
                   if (data.success) {
                       this.modalPreviewData = data.data;
                       this.modalCustomInvoiceMappings = {};
                       this.modalPreviewData.forEach(item => {
                           if (item.detail_invoices && item.detail_invoices.length) {
                               this.modalCustomInvoiceMappings[item.id] = {};
                               item.detail_invoices.forEach(inv => {
                                   this.modalCustomInvoiceMappings[item.id][inv.old_number] = inv.mapped_number || inv.old_number;
                               });
                           }
                       });
                       this.handleNumberingModeChange();
                   }
               } catch (e) {
                   console.error('Failed to fetch preview data', e);
               } finally {
                   this.modalPreviewLoading = false;
               }
           },

           handleNumberingModeChange() {
               if (this.modalNumberingMode === 'custom') {
                   this.modalPreviewData.forEach(item => {
                       this.modalTargetNumbers[item.id] = item.generated_number;
                   });
               } else if (this.modalNumberingMode === 'original') {
                   this.modalPreviewData.forEach(item => {
                       this.modalTargetNumbers[item.id] = item.old_number;
                   });
               } else {
                   this.modalPreviewData.forEach(item => {
                       this.modalTargetNumbers[item.id] = '';
                   });
               }
           },

           closeMigrateModal() {
               this.showTargetDbModal = false;
               this.modalTargetIds = [];
               this.modalAddJuSuffix = false;
               this.modalPreviewData = [];
           },

           migrateSuccessCount: 0,
           migrateFailedCount: 0,
           migrateTotalSelected: 0,
       
           selectAllTransactions() {
               if (this.selectAll) {
                   this.selected = this.allTransactionIds.map(id => String(id));
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
           },
       
           async openEditModal(transactionId, transactionNo) {
               this.editTransaction = { id: transactionId, no: transactionNo };
               this.editMode = 'form';
               this.searchField = '';
               this.editLoading = true;
               this.showEditModal = true;
       
               try {
                   const response = await fetch(`/migrate/${transactionId}/data`, {
                       headers: {
                           'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest',
                       }
                   });
       
                   if (!response.ok) {
                       throw new Error('Failed to load transaction data');
                   }
       
                   const payload = await response.json();
                   const parsed = payload?.data && typeof payload.data === 'object' ? payload.data : {};
       
                   this.parsedData = parsed;
                   this.editData = JSON.stringify(parsed, null, 2);
               } catch (e) {
                   this.parsedData = {};
                   this.editData = '{}';
               } finally {
                   this.editLoading = false;
               }
           },
       
           closeEditModal() {
               this.showEditModal = false;
               this.editTransaction = null;
               this.editData = '';
               this.parsedData = {};
               this.editMode = 'form';
               this.searchField = '';
           },
       
           switchToForm() {
               try {
                   this.parsedData = JSON.parse(this.editData);
                   this.editMode = 'form';
               } catch (e) {
                   alert('Invalid JSON. Please fix errors before switching to form mode.');
               }
           },
       
           switchToJson() {
               this.editData = JSON.stringify(this.parsedData, null, 2);
               this.editMode = 'json';
           },
       
           updateValue(path, value) {
               const keys = path.split('.');
               let obj = this.parsedData;
               for (let i = 0; i < keys.length - 1; i++) {
                   obj = obj[keys[i]];
               }
               // Convert empty string to null if original type was null
               obj[keys[keys.length - 1]] = value;
           },
       
           getValue(path) {
               const keys = path.split('.');
               let obj = this.parsedData;
               for (let key of keys) {
                   if (obj === null || obj === undefined) return '';
                   obj = obj[key];
               }
               return obj ?? '';
           },
       
           getFieldEntries() {
               const entries = [];
               const process = (obj, prefix = '') => {
                   for (let key in obj) {
                       const path = prefix ? `${prefix}.${key}` : key;
                       const value = obj[key];
       
                       // Check for null first
                       if (value === null) {
                           entries.push({ key: path, value: null, type: 'null', displayKey: key });
                       } else if (Array.isArray(value)) {
                           entries.push({ key: path, value: JSON.stringify(value), type: 'array', displayKey: key });
                       } else if (typeof value === 'object') {
                           entries.push({ key: path, value: null, type: 'object', displayKey: key });
                           process(value, path);
                       } else {
                           entries.push({ key: path, value: value, type: typeof value, displayKey: key });
                       }
                   }
               };
               process(this.parsedData);
               return entries;
           },
       
           filteredFields() {
               const fields = this.getFieldEntries();
               if (!this.searchField) return fields;
               const search = this.searchField.toLowerCase();
               return fields.filter(f =>
                   f.key.toLowerCase().includes(search) ||
                   (f.value && String(f.value).toLowerCase().includes(search))
               );
           },
       
           saveEdit() {
               let finalData;
               if (this.editMode === 'form') {
                   finalData = JSON.stringify(this.parsedData);
               } else {
                   finalData = this.editData;
               }
       
               // Validate JSON before submitting
               try {
                   JSON.parse(finalData);
                   document.getElementById('editDataInput').value = finalData;
                   $refs.editForm.submit();
               } catch (e) {
                   alert('Invalid JSON format: ' + e.message);
               }
           },
       
           formatEditJson() {
               try {
                   const parsed = JSON.parse(this.editData);
                   this.editData = JSON.stringify(parsed, null, 2);
               } catch (e) {
                   alert('Invalid JSON: ' + e.message);
               }
           },
       
           saveMigrateMonitorState() {
               localStorage.setItem('migrateMonitorState', JSON.stringify({
                   migrateMonitorId: this.migrateMonitorId,
                   migrateMonitorVisible: this.migrateMonitorVisible,
                   migrating: this.migrating,
                   progress: this.progress,
                   currentStatus: this.currentStatus,
                   migrateSuccessCount: this.migrateSuccessCount,
                   migrateFailedCount: this.migrateFailedCount,
                   migrateTotalSelected: this.migrateTotalSelected,
               }));
           },
       
           clearMigrateMonitorState() {
               localStorage.removeItem('migrateMonitorState');
           },
       
           async restoreMigrateMonitorState() {
               const raw = localStorage.getItem('migrateMonitorState');
               if (!raw) return false;
       
               try {
                   const state = JSON.parse(raw);
                   if (!state?.migrateMonitorId) return false;
       
                   const statusResponse = await fetch(`/system-logs/${state.migrateMonitorId}/status`, {
                       method: 'GET',
                       credentials: 'same-origin',
                       headers: {
                           'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest',
                       },
                   });
       
                   if (!statusResponse.ok) {
                       this.clearMigrateMonitorState();
                       return false;
                   }
       
                   const statusResult = await statusResponse.json();
                   const trackerStatus = statusResult?.status;
                   if (!['queued', 'running'].includes(trackerStatus)) {
                       this.clearMigrateMonitorState();
                       return false;
                   }
       
                   this.migrateMonitorId = state.migrateMonitorId;
                   this.migrateMonitorVisible = true;
                   this.migrating = true;
                   this.progress = Number(state.progress || 0);
                   this.currentStatus = state.currentStatus || 'Resuming migration monitor...';
                   this.migrateSuccessCount = Number(state.migrateSuccessCount || 0);
                   this.migrateFailedCount = Number(state.migrateFailedCount || 0);
                   this.migrateTotalSelected = Number(state.migrateTotalSelected || 0);
       
                   this.pollMigrateStatus();
                   return true;
               } catch (e) {
                   this.clearMigrateMonitorState();
                   return false;
               }
           },
       
           async restoreMigrateMonitorFromServer() {
               try {
                   const response = await fetch('/system-logs/active?event_type=migrate_queue', {
                       method: 'GET',
                       credentials: 'same-origin',
                       headers: {
                           'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest',
                       },
                   });
       
                   if (!response.ok) {
                       return false;
                   }
       
                   const result = await response.json();
                   if (!(result?.success && result?.active && result?.log?.id)) {
                       return false;
                   }
       
                   const log = result.log;
                   const payload = log?.payload || {};
       
                   this.migrateMonitorId = log.id;
                   this.migrateMonitorVisible = true;
                   this.migrating = true;
                   this.progress = Number(payload?.progress || 0);
                   this.currentStatus = log.message || 'Migration in progress';
                   this.migrateSuccessCount = Number(payload?.success_count || 0);
                   this.migrateFailedCount = Number(payload?.failed_count || 0);
                   this.migrateTotalSelected = Number(payload?.total_selected || 0);
       
                   this.saveMigrateMonitorState();
                   this.pollMigrateStatus();
                   return true;
               } catch (e) {
                   return false;
               }
           },
       
           async initMigrateMonitor() {
               const hasRestoredLocal = await this.restoreMigrateMonitorState();
       
               try {
                   if (!hasRestoredLocal) {
                       await this.restoreMigrateMonitorFromServer();
                   }
               } finally {
                   this.migrateMonitorBooting = false;
               }
           },
       
           async pollMigrateStatus() {
               if (!this.migrateMonitorId) return;
       
               while (true) {
                   await new Promise(resolve => setTimeout(resolve, 1500));
                   const statusResponse = await fetch(`/system-logs/${this.migrateMonitorId}/status`, {
                       method: 'GET',
                       credentials: 'same-origin',
                       headers: {
                           'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest',
                       },
                   });
       
                   if (!statusResponse.ok) {
                       continue;
                   }
       
                   const statusResult = await statusResponse.json();
                   const payload = statusResult?.payload || {};
                   const trackerStatus = statusResult?.status;
       
                   if (typeof payload?.progress === 'number') {
                       this.progress = Math.max(this.progress, Math.min(100, payload.progress));
                   }
       
                   this.migrateSuccessCount = Number(payload?.success_count || 0);
                   this.migrateFailedCount = Number(payload?.failed_count || 0);
                   this.migrateTotalSelected = Number(payload?.total_selected || this.migrateTotalSelected);
                   this.currentStatus = statusResult?.message || this.currentStatus;
                   this.saveMigrateMonitorState();
       
                   if (['success', 'warning', 'info', 'failed'].includes(trackerStatus)) {
                       this.progress = 100;
                       this.migrating = false;
       
                       if (trackerStatus === 'success' || trackerStatus === 'warning' || trackerStatus === 'info') {
                           this.clearAll();
                       }
       
                       this.clearMigrateMonitorState();
                       break;
                   }
               }
           },
       
           async executeMigration() {
               if (!this.modalSelectedDbId) {
                   alert('Please select a target database!');
                   return;
               }

               // Copy variables before closing the modal
               const targetDbId = this.modalSelectedDbId;
               const payloadIds = [...this.modalTargetIds];
               const forceCreate = this.modalForceCreate;
               const addJuSuffix = this.modalAddJuSuffix;
               const numberingMode = this.modalNumberingMode;

               this.closeMigrateModal();
               this.migrating = true;
               this.migrateMonitorVisible = true;
               this.migrateMonitorId = null;
               this.progress = 0;
               this.currentStatus = 'Preparing migration...';
               this.migrateSuccessCount = 0;
               this.migrateFailedCount = 0;
               this.migrateTotalSelected = payloadIds.length;

               try {
                   const response = await fetch('{{ route('migrate.toAccurate') }}', {
                       method: 'POST',
                       credentials: 'same-origin',
                       headers: {
                           'Content-Type': 'application/json',
                           'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest',
                           'X-CSRF-TOKEN': '{{ csrf_token() }}'
                       },
                       body: JSON.stringify({
                           target_database_id: targetDbId,
                           ids: payloadIds,
                           force_create: forceCreate,
                           add_ju_suffix: addJuSuffix,
                           numbering_mode: numberingMode,
                           target_numbers: this.modalTargetNumbers,
                           custom_invoice_mappings: this.modalCustomInvoiceMappings
                       })
                   });

                   const result = await response.json();
                   if (response.ok && result?.success && result?.queued && result?.monitor_id) {
                       this.migrateMonitorId = result.monitor_id;
                       this.currentStatus = result?.message || 'Migration queued';
                       this.saveMigrateMonitorState();
                       await this.pollMigrateStatus();
                   } else {
                       this.migrating = false;
                       let errorMsg = result.message || 'Failed to start migration';
                       if (result.errors) {
                           errorMsg += '\n\nDetail:\n' + Object.values(result.errors).flat().join('\n');
                       }
                       this.currentStatus = errorMsg;
                       alert(this.currentStatus);
                   }
               } catch (error) {
                   this.migrating = false;
                   this.currentStatus = 'Network error while starting migration.';
                   alert(this.currentStatus);
               }
           }
       }"
       x-init="initMigrateMonitor();
       $watch('selected', value => selectAll = allTransactionIds.length > 0 && allTransactionIds.every(id => value.includes(String(id))))">
    <div class="w-full bg-gradient-to-r from-green-600 to-green-700 rounded-xl p-5 md:p-8 lg:p-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 lg:gap-4">
      <div class="flex flex-col gap-4 md:gap-5 w-full lg:w-auto">
        <div class="flex items-center gap-3 md:gap-4">
          <svg xmlns="http://www.w3.org/2000/svg"
               fill="none"
               viewBox="0 0 24 24"
               stroke-width="1.5"
               stroke="#FFFFFF"
               class="w-5 h-5 md:w-6 md:h-6 flex-shrink-0">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
          </svg>
          <div class="flex flex-col">
            <p class="text-white font-normal text-xs md:text-sm tracking-wide">Source Database (Current Session)</p>
            <p class="text-white text-sm md:text-base lg:text-lg font-semibold">Data captured from this database</p>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
          <div class="bg-white/20 backdrop-blur-md border border-white/30 text-white text-xs md:text-sm rounded-lg w-full p-2 md:p-2.5 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg"
                   fill="none"
                   viewBox="0 0 24 24"
                   stroke-width="1.5"
                   stroke="currentColor"
                   class="size-4">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
              </svg>
              <span class="font-medium">{{ $current_database_name ?? 'No database selected' }}</span>
            </div>
            <a href="{{ route('modules.index') }}"
               class="text-white/80 hover:text-white text-xs underline">
              Change
            </a>
          </div>
        </div>
      </div>

      <div class="flex flex-col gap-4 md:gap-5 w-full lg:w-auto">
        <div class="flex items-center gap-3 md:gap-4">
          <svg xmlns="http://www.w3.org/2000/svg"
               fill="none"
               viewBox="0 0 24 24"
               stroke-width="1.5"
               stroke="#FFFFFF"
               class="w-5 h-5 md:w-6 md:h-6 flex-shrink-0">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
          <div class="flex flex-col">
            <p class="text-white font-normal text-xs md:text-sm tracking-wide">Database Target</p>
            <p class="text-white text-sm md:text-base lg:text-lg font-semibold">Pilih database tujuan migrasi</p>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
          <div x-data="{
              open: false,
              selected: 'Pilih Database Target',
              selectedId: null
          }"
               class="relative w-full">
            <input type="hidden"
                   id="targetDatabaseSelect"
                   x-model="selectedId">
            <button @click="open = !open"
                    type="button"
                    class="bg-white/20 backdrop-blur-md border border-white/30 text-white text-xs md:text-sm rounded-lg focus:ring-white/50 focus:border-white/50 w-full p-2 md:p-2.5 text-left flex items-center justify-between">
              <span x-text="selected"></span>
              <svg xmlns="http://www.w3.org/2000/svg"
                   fill="none"
                   viewBox="0 0 24 24"
                   stroke-width="1.5"
                   stroke="currentColor"
                   class="size-5 transition-transform"
                   :class="{ 'rotate-180': open }">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      d="m19.5 8.25-7.5 7.5-7.5-7.5" />
              </svg>
            </button>

            <div x-show="open"
                 @click.away="open = false"
                 x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute z-[999] w-full mt-2 bg-white border border-white/30 rounded-lg shadow-lg overflow-hidden max-h-60 overflow-y-auto">
              <ul class="py-1">
                @forelse($databases as $db)
                  <li @click="selected = '{{ $db['alias'] }}'; selectedId = {{ $db['id'] }}; open = false"
                      :class="selectedId === {{ $db['id'] }} ? 'bg-blue-50' : ''"
                      class="px-4 py-2 text-black text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                    <span>{{ $db['alias'] }}</span>
                    <svg x-show="selectedId === {{ $db['id'] }}"
                         xmlns="http://www.w3.org/2000/svg"
                         fill="none"
                         viewBox="0 0 24 24"
                         stroke-width="2"
                         stroke="currentColor"
                         class="size-5 text-blue-600">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
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
        </div>
      </div>

      <div class="flex flex-row sm:flex-row items-center justify-between sm:justify-start gap-6 sm:gap-8 md:gap-10 w-full lg:w-auto">
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

    <!-- Target Database Info Alert -->
    <div x-data="{
        targetDbName: '',
        showAlert: false,
        databases: {{ Js::from($databases) }},
        updateAlert() {
            const select = document.getElementById('targetDatabaseSelect');
            if (select && select.value) {
                const selectedDb = this.databases.find(db => db.id == select.value);
                if (selectedDb) {
                    this.targetDbName = selectedDb.alias;
                    this.showAlert = true;
                } else {
                    this.showAlert = false;
                }
            } else {
                this.showAlert = false;
            }
        }
    }"
         @click.window="updateAlert()"
         x-init="const select = document.getElementById('targetDatabaseSelect');
         if (select) {
             const observer = new MutationObserver(() => { updateAlert(); });
             observer.observe(select, { attributes: true, attributeFilter: ['x-model'] });
         }">
      <div x-show="showAlert"
           x-cloak
           x-transition
           class="bg-green-50 border border-green-200 flex flex-col sm:flex-row gap-3 p-4 rounded-lg">
        <svg xmlns="http://www.w3.org/2000/svg"
             viewBox="0 0 24 24"
             fill="currentColor"
             class="size-6 flex-shrink-0 text-green-600">
          <path fill-rule="evenodd"
                d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                clip-rule="evenodd" />
        </svg>
        <div class="flex flex-col gap-1">
          <p class="text-base text-green-800 font-medium">Database Tujuan Terpilih</p>
          <p class="text-lg text-green-900 font-bold"
             x-text="targetDbName"></p>
          <p class="text-green-700 font-normal text-sm">
            Transaksi akan dimigrasikan ke database ini.
          </p>
        </div>
      </div>
    </div>

    @if (session('success'))
      <div x-data="{ show: true }"
           x-show="show"
           class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-start gap-3 flex-1">
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="2"
                 stroke="currentColor"
                 class="w-5 h-5 flex-shrink-0 mt-0.5">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <div class="flex-1">
              @php
                $successMessage = session('success');
                // Parse success message format: "Migration completed: X succeeded, Y failed. Errors: ..."
                preg_match('/(\d+)\s+succeeded(?:,\s+(\d+)\s+failed)?/', $successMessage, $matches);
                $succeeded = $matches[1] ?? 0;
                $failed = $matches[2] ?? 0;
              @endphp

              <p class="font-semibold text-base">Migrasi Selesai</p>
              <div class="mt-2 space-y-1">
                <p class="text-sm"><span class="font-semibold text-green-700">{{ $succeeded }}</span> transaksi berhasil dimigrasikan</p>
                @if ($failed > 0)
                  <p class="text-sm"><span class="font-semibold text-red-600">{{ $failed }}</span> transaksi gagal</p>
                @endif
              </div>
            </div>
          </div>
          <button @click="show = false"
                  class="text-green-600 hover:text-green-800 flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="2"
                 stroke="currentColor"
                 class="w-5 h-5">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    @elseif(session('delete_success'))
      <div x-data="{ show: true }"
           x-show="show"
           class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-start gap-3 flex-1">
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="2"
                 stroke="currentColor"
                 class="w-5 h-5 flex-shrink-0 mt-0.5">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <div class="flex-1">
              <p class="font-semibold text-base">Transaksi Dihapus</p>
              <p class="text-sm mt-1">{{ session('delete_success') }}</p>
            </div>
          </div>
          <button @click="show = false"
                  class="text-green-600 hover:text-green-800 flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="2"
                 stroke="currentColor"
                 class="w-5 h-5">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    @elseif(session('edit_success'))
      <div x-data="{ show: true }"
           x-show="show"
           class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-start gap-3 flex-1">
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="2"
                 stroke="currentColor"
                 class="w-5 h-5 flex-shrink-0 mt-0.5">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <div class="flex-1">
              <p class="font-semibold text-base">Data Diperbarui</p>
              <p class="text-sm mt-1">{{ session('edit_success') }}</p>
            </div>
          </div>
          <button @click="show = false"
                  class="text-green-600 hover:text-green-800 flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="2"
                 stroke="currentColor"
                 class="w-5 h-5">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    @elseif(session('error'))
      <div x-data="{ show: true }"
           x-show="show"
           class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-start gap-3 flex-1">
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="2"
                 stroke="currentColor"
                 class="w-5 h-5 flex-shrink-0 mt-0.5">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <div class="flex-1">
              @php
                $errorMessage = session('error');
                // Parse error message: "Migration completed: X succeeded, Y failed. Details: Module1 (Success: X, Failed: Y) - Errors: error1, error2; ..."
                $parts = explode('. Details: ', $errorMessage);
                $summary = $parts[0] ?? $errorMessage;
                $moduleDetails = $parts[1] ?? null;

                // Extract counts from summary
                preg_match('/(\d+)\s+succeeded(?:,\s+(\d+)\s+failed)?/', $summary, $matches);
                $succeeded = $matches[1] ?? 0;
                $failed = $matches[2] ?? 0;

                // Parse module details: "ModuleName (Success: X, Failed: Y) - Errors: error1, error2"
                $parsedModules = [];
                if ($moduleDetails) {
                    $moduleList = array_map('trim', explode(';', $moduleDetails));
                    foreach ($moduleList as $moduleInfo) {
                        // Match: "ModuleName (Success: X, Failed: Y) - Errors: error messages"
                        if (preg_match('/^([^\(]+)\s*\(Success:\s*(\d+),\s*Failed:\s*(\d+)\)(?:\s*-\s*Errors:\s*(.+))?$/', $moduleInfo, $moduleMatches)) {
                            $parsedModules[] = [
                                'name' => trim($moduleMatches[1]),
                                'success' => (int) $moduleMatches[2],
                                'failed' => (int) $moduleMatches[3],
                                'errors' => isset($moduleMatches[4]) ? array_map('trim', explode(',', $moduleMatches[4])) : [],
                            ];
                        }
                    }
                }
              @endphp

              <p class="font-semibold text-base">Migrasi Selesai dengan Error</p>
              <div class="mt-2 space-y-1">
                @if ($succeeded > 0)
                  <p class="text-sm"><span class="font-semibold text-green-700">{{ $succeeded }}</span> transaksi berhasil dimigrasikan</p>
                @endif
                @if ($failed > 0)
                  <p class="text-sm"><span class="font-semibold text-red-600">{{ $failed }}</span> transaksi gagal</p>
                @endif
              </div>

              @if (!empty($parsedModules))
                <div class="mt-3 space-y-2">
                  <p class="text-sm font-medium">Rincian Modul:</p>
                  @foreach ($parsedModules as $moduleInfo)
                    <div class="bg-red-100/50 rounded-lg p-3 border border-red-300">
                      <div class="flex items-start justify-between gap-2 mb-2">
                        <p class="font-semibold text-sm flex items-center gap-2">
                          <svg xmlns="http://www.w3.org/2000/svg"
                               fill="none"
                               viewBox="0 0 24 24"
                               stroke-width="2"
                               stroke="currentColor"
                               class="w-4 h-4">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  d="M6 6h.008v.008H6V6Z" />
                          </svg>
                          {{ $moduleInfo['name'] }}
                        </p>
                        <div class="flex items-center gap-3 text-xs font-medium">
                          @if ($moduleInfo['success'] > 0)
                            <span class="bg-green-100 text-green-700 px-2 py-1 rounded">✓ {{ $moduleInfo['success'] }}</span>
                          @endif
                          @if ($moduleInfo['failed'] > 0)
                            <span class="bg-red-200 text-red-700 px-2 py-1 rounded">✗ {{ $moduleInfo['failed'] }}</span>
                          @endif
                        </div>
                      </div>
                      @if (!empty($moduleInfo['errors']))
                        <div class="mt-2 bg-white/50 rounded p-2">
                          <p class="text-xs font-medium text-red-800 mb-1">Error causes:</p>
                          <ul class="space-y-1">
                            @foreach ($moduleInfo['errors'] as $error)
                              <li class="text-xs flex items-start gap-2">
                                <span class="text-red-600 mt-0.5">•</span>
                                <span class="flex-1">{{ $error }}</span>
                              </li>
                            @endforeach
                          </ul>
                        </div>
                      @endif
                    </div>
                  @endforeach
                </div>
              @endif
            </div>
          </div>
          <button @click="show = false"
                  class="text-red-600 hover:text-red-800 flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="2"
                 stroke="currentColor"
                 class="w-5 h-5">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5 lg:gap-7">
      <div class="flex border border-gray-200 min-h-[120px] md:min-h-[150px] items-center justify-between shadow-md rounded-xl bg-white p-5 md:p-7 md:pt-10">
        <div class="flex flex-col gap-1">
          <p class="text-gray-500 text-sm md:text-base">Total</p>
          <p class="text-black text-2xl md:text-3xl font-medium">{{ $total }}</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg"
             viewBox="0 0 24 24"
             fill="currentColor"
             class="w-8 h-8 md:w-10 md:h-10 text-gray-300">
          <path d="M21 6.375c0 2.692-4.03 4.875-9 4.875S3 9.067 3 6.375 7.03 1.5 12 1.5s9 2.183 9 4.875Z" />
          <path d="M12 12.75c2.685 0 5.19-.586 7.078-1.609a8.283 8.283 0 0 0 1.897-1.384c.016.121.025.244.025.368C21 12.817 16.97 15 12 15s-9-2.183-9-4.875c0-.124.009-.247.025-.368a8.285 8.285 0 0 0 1.897 1.384C6.809 12.164 9.315 12.75 12 12.75Z" />
          <path d="M12 16.5c2.685 0 5.19-.586 7.078-1.609a8.282 8.282 0 0 0 1.897-1.384c.016.121.025.244.025.368 0 2.692-4.03 4.875-9 4.875s-9-2.183-9-4.875c0-.124.009-.247.025-.368a8.284 8.284 0 0 0 1.897 1.384C6.809 15.914 9.315 16.5 12 16.5Z" />
          <path d="M12 20.25c2.685 0 5.19-.586 7.078-1.609a8.282 8.282 0 0 0 1.897-1.384c.016.121.025.244.025.368 0 2.692-4.03 4.875-9 4.875s-9-2.183-9-4.875c0-.124.009-.247.025-.368a8.284 8.284 0 0 0 1.897 1.384C6.809 19.664 9.315 20.25 12 20.25Z" />
        </svg>
      </div>
      <div class="flex border border-gray-200 min-h-[120px] md:min-h-[150px] items-center justify-between shadow-md rounded-xl bg-white p-5 md:p-7 md:pt-10">
        <div class="flex flex-col gap-1">
          <p class="text-gray-500 text-sm md:text-base">Pending</p>
          <p class="text-orange-600 text-2xl md:text-3xl font-medium">{{ $pending }}</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg"
             fill="none"
             viewBox="0 0 24 24"
             stroke-width="1.5"
             stroke="currentColor"
             class="w-8 h-8 md:w-10 md:h-10 text-orange-300">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
      </div>
      <div class="flex border border-gray-200 min-h-[120px] md:min-h-[150px] items-center justify-between shadow-md rounded-xl bg-white p-5 md:p-7 md:pt-10">
        <div class="flex flex-col gap-1">
          <p class="text-gray-500 text-sm md:text-base">Migrated</p>
          <p class="text-green-600 text-2xl md:text-3xl font-medium">{{ $success }}</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg"
             fill="none"
             viewBox="0 0 24 24"
             stroke-width="1.5"
             stroke="currentColor"
             class="w-8 h-8 md:w-10 md:h-10 text-green-300">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
      </div>
      <div class="flex border border-gray-200 min-h-[120px] md:min-h-[150px] items-center justify-between shadow-md rounded-xl bg-white p-5 md:p-7 md:pt-10">
        <div class="flex flex-col gap-1">
          <p class="text-gray-500 text-sm md:text-base">Failed</p>
          <p class="text-red-600 text-2xl md:text-3xl font-medium">{{ $failed }}</p>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg"
             fill="none"
             viewBox="0 0 24 24"
             stroke-width="1.5"
             stroke="currentColor"
             class="w-8 h-8 md:w-10 md:h-10 text-red-300">
          <path stroke-linecap="round"
                stroke-linejoin="round"
                d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
      </div>
    </div>

    <div class="flex flex-col gap-4 md:gap-5 rounded-xl bg-white shadow-lg p-4 md:p-5 border border-gray-200">
      <div class="flex max-sm:flex-col max-sm:items-start max-sm:gap-3 items-center justify-between">
        <div x-show="!migrateMonitorBooting && selected.length > 0 && !migrating"
             x-cloak
             class="flex flex-col gap-1">
          <div class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="1.5"
                 stroke="currentColor"
                 class="w-5 h-5 md:w-6 md:h-6 text-blue-500">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
            </svg>
            <p class="text-sm md:text-base text-black font-medium">Manajer Transaksi</p>
          </div>
          <p class="text-xs md:text-sm lg:text-base text-gray-500 font-normal">Pilih dan migrasikan transaksi ke database tujuan yang dipilih
          </p>
        </div>

        <div x-show="!migrateMonitorBooting && selected.length > 0 && !migrating"
             x-cloak
             class="flex self-end gap-2">
          <button @click="confirmDelete()"
                  type="button"
                  class="bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-4 md:px-5 py-2 md:py-2.5 rounded-lg transition flex items-center gap-2 text-sm md:text-base border border-red-300">
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="1.5"
                 stroke="currentColor"
                 class="w-5 h-5">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
            </svg>
            <span>Hapus <span x-text="selected.length"></span> Terpilih</span>
          </button>
          <button @click.prevent="openMigrateModal()"
                  type="button"
                  :disabled="migrating"
                  class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 md:px-6 py-2 md:py-2.5 rounded-lg transition flex items-center gap-2 text-sm md:text-base disabled:opacity-60 disabled:cursor-not-allowed">
            <svg x-show="!migrating"
                 xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="1.5"
                 stroke="currentColor"
                 class="w-5 h-5">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
            <svg x-show="migrating"
                 class="animate-spin w-5 h-5"
                 xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24">
              <circle class="opacity-25"
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      stroke-width="4"></circle>
              <path class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span x-show="!migrating">Migrate <span x-text="selected.length"></span> Selected</span>
            <span x-show="migrating">Migrating...</span>
          </button>
        </div>

        <div x-show="!migrateMonitorBooting && migrateMonitorVisible"
             x-cloak
             x-transition.opacity.duration.200ms
             class="w-full rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 md:p-5 flex flex-col gap-4 mt-4">
          <div class="flex items-start justify-between gap-3">
            <div class="flex flex-col">
              <div class="flex items-center gap-2">
                <p class="text-sm font-semibold text-blue-900">Migrate Monitor</p>
                <span class="text-[11px] px-2 py-0.5 rounded-full border"
                      :class="migrating
                          ?
                          'bg-blue-100 border-blue-200 text-blue-700' :
                          (migrateFailedCount > 0 ?
                              'bg-amber-100 border-amber-200 text-amber-700' :
                              'bg-green-100 border-green-200 text-green-700')"
                      x-text="migrating ? 'Running' : (migrateFailedCount > 0 ? 'Done with warning' : 'Completed')"></span>
              </div>
              <p class="text-sm text-blue-700"
                 x-text="currentStatus || 'Menunggu progress...' "></p>
              <p class="text-xs text-blue-600 mt-1">
                Processed
                <span class="font-semibold"
                      x-text="migrateSuccessCount + migrateFailedCount"></span>
                /
                <span class="font-semibold"
                      x-text="migrateTotalSelected"></span>
              </p>
            </div>
            <button type="button"
                    x-show="!migrating"
                    @click="migrateMonitorVisible = false; clearMigrateMonitorState()"
                    class="text-xs px-2 py-1 rounded border border-blue-300 text-blue-700 hover:bg-blue-100">Tutup</button>
          </div>

          <div class="space-y-1">
            <div class="flex justify-between text-xs text-blue-800">
              <span>Progress</span>
              <span class="font-semibold"
                    x-text="progress + '%' "></span>
            </div>
            <div class="w-full bg-blue-100 rounded-full h-2.5 overflow-hidden">
              <div class="bg-gradient-to-r from-blue-600 to-indigo-500 h-2.5 rounded-full transition-all duration-500"
                   :style="`width: ${progress}%`"></div>
            </div>
          </div>

          <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
            <div class="rounded-lg bg-white border border-blue-100 px-3 py-2">
              <p class="text-gray-500">Success</p>
              <p class="font-semibold text-green-600"
                 x-text="migrateSuccessCount"></p>
            </div>
            <div class="rounded-lg bg-white border border-blue-100 px-3 py-2">
              <p class="text-gray-500">Failed</p>
              <p class="font-semibold text-red-600"
                 x-text="migrateFailedCount"></p>
            </div>
            <div class="rounded-lg bg-white border border-blue-100 px-3 py-2">
              <p class="text-gray-500">Selected</p>
              <p class="font-semibold text-blue-700"
                 x-text="migrateTotalSelected"></p>
            </div>
            <div class="rounded-lg bg-white border border-blue-100 px-3 py-2">
              <p class="text-gray-500">Success Rate</p>
              <p class="font-semibold text-blue-700"
                 x-text="(migrateSuccessCount + migrateFailedCount) > 0
                  ? Math.round((migrateSuccessCount / (migrateSuccessCount + migrateFailedCount)) * 100) + '%'
                  : '0%'"></p>
            </div>
          </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div x-show="showDeleteModal"
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             aria-labelledby="modal-title"
             role="dialog"
             aria-modal="true">
          <div class="flex items-center justify-center min-h-screen p-4">
            <!-- Background overlay -->
            <div x-show="showDeleteModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                 @click="showDeleteModal = false"></div>

            <!-- Modal panel -->
            <div x-show="showDeleteModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-lg">
              <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                  <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-red-600"
                         xmlns="http://www.w3.org/2000/svg"
                         fill="none"
                         viewBox="0 0 24 24"
                         stroke-width="2"
                         stroke="currentColor">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                  </div>
                  <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg leading-6 font-medium text-gray-900"
                        id="modal-title">
                      Hapus Transaksi
                    </h3>
                    <div class="mt-2">
                      <p class="text-sm text-gray-500">
                        Apakah Anda yakin ingin menghapus <span class="font-semibold"
                              x-text="selected.length"></span> transaksi terpilih? Tindakan ini tidak dapat dibatalkan.
                      </p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                <button type="button"
                        @click="deleteSelected()"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                  Hapus
                </button>
                <button type="button"
                        @click="showDeleteModal = false"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                  Batal
                </button>
              </div>
            </div>
          </div>
        </div>


      </div>

      <form method="GET"
            action="{{ route('migrate.index') }}"
            id="filterForm"
            class="w-full rounded-xl bg-gray-50 p-3 md:p-4 border border-gray-200 flex flex-wrap items-stretch md:items-end gap-2 md:gap-3">
        <input type="text"
               name="search"
               placeholder="Cari nomor transaksi / lama..."
               value="{{ request('search') }}"
               class="bg-white rounded-md py-2 px-3 md:px-4 border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs md:text-sm w-full md:w-[220px] lg:w-[260px] font-medium">

        <input type="text"
               name="new_number"
               placeholder="Cari nomor baru..."
               value="{{ request('new_number') }}"
               class="bg-white rounded-md py-2 px-3 md:px-4 border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs md:text-sm w-full md:w-[220px] lg:w-[260px] font-medium">

        <!-- All Database Dropdown -->
        <div x-data="{ open: false, selected: '{{ request('source_db', 'All Database') }}' }"
             class="relative w-full md:w-auto">
          <input type="hidden"
                 name="source_db"
                 :value="selected !== 'All Database' ? selected : ''">
          <button @click="open = !open"
                  type="button"
                  class="bg-white w-full md:min-w-[180px] lg:min-w-[200px] border border-gray-200 text-gray-700 text-xs md:text-sm rounded-md py-2 px-3 md:px-4 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center gap-2 whitespace-nowrap">
            <span x-text="selected"
                  class="font-medium"></span>
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="1.5"
                 stroke="currentColor"
                 class="size-4 transition-transform ml-auto"
                 :class="{ 'rotate-180': open }">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
          </button>
          <div x-show="open"
               @click.away="open = false"
               x-cloak
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="opacity-0 scale-95"
               x-transition:enter-end="opacity-100 scale-100"
               x-transition:leave="transition ease-in duration-150"
               x-transition:leave-start="opacity-100 scale-100"
               x-transition:leave-end="opacity-0 scale-95"
               class="absolute z-[99] mt-2 bg-white border border-gray-200 rounded-md shadow-lg overflow-hidden w-full md:min-w-[200px]">
            <ul class="py-1 max-h-[200px] md:max-h-none overflow-y-auto">
              <li @click="selected = 'All Database'; open = false"
                  :class="selected === 'All Database' ? 'bg-blue-50' : ''"
                  class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                <span>All Database</span>
                <svg x-show="selected === 'All Database'"
                     xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="2"
                     stroke="currentColor"
                     class="size-4 text-blue-600">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        d="m4.5 12.75 6 6 9-13.5" />
                </svg>
              </li>
              @foreach ($filter_databases as $db)
                <li @click="selected = '{{ $db }}'; open = false"
                    :class="selected === '{{ $db }}' ? 'bg-blue-50' : ''"
                    class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                  <span>{{ $db }}</span>
                  <svg x-show="selected === '{{ $db }}'"
                       xmlns="http://www.w3.org/2000/svg"
                       fill="none"
                       viewBox="0 0 24 24"
                       stroke-width="2"
                       stroke="currentColor"
                       class="size-4 text-blue-600">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          d="m4.5 12.75 6 6 9-13.5" />
                  </svg>
                </li>
              @endforeach
            </ul>
          </div>
        </div>

        <!-- All Modules Dropdown -->
        <div x-data="{ open: false }"
             class="relative w-full md:w-auto">
          <input type="hidden"
                 name="module"
                 :value="filterModuleSelected !== 'All Modules' ? filterModuleSelected : ''">
          <button @click="open = !open"
                  type="button"
                  class="bg-white border w-full md:min-w-[180px] lg:min-w-[200px] border-gray-200 text-gray-700 text-xs md:text-sm rounded-md py-2 px-3 md:px-4 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center gap-2 whitespace-nowrap">
            <span x-text="filterModuleSelected"
                  class="font-medium"></span>
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="1.5"
                 stroke="currentColor"
                 class="size-4 transition-transform ml-auto"
                 :class="{ 'rotate-180': open }">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
          </button>
          <div x-show="open"
               @click.away="open = false"
               x-cloak
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="opacity-0 scale-95"
               x-transition:enter-end="opacity-100 scale-100"
               x-transition:leave="transition ease-in duration-150"
               x-transition:leave-start="opacity-100 scale-100"
               x-transition:leave-end="opacity-0 scale-95"
               class="absolute z-[99] mt-2 bg-white border border-gray-200 rounded-md shadow-lg overflow-hidden w-full">
            <ul class="py-1 max-h-[250px] overflow-y-auto">
              <li @click="filterModuleSelected = 'All Modules'; open = false"
                  :class="filterModuleSelected === 'All Modules' ? 'bg-blue-50' : ''"
                  class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                <span>All Modules</span>
                <svg x-show="filterModuleSelected === 'All Modules'"
                     xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="2"
                     stroke="currentColor"
                     class="size-4 text-blue-600">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        d="m4.5 12.75 6 6 9-13.5" />
                </svg>
              </li>
              @foreach ($modules as $module)
                <li @click="filterModuleSelected = '{{ $module }}'; open = false"
                    :class="filterModuleSelected === '{{ $module }}' ? 'bg-blue-50' : ''"
                    class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                  <span>{{ $module }}</span>
                  <svg x-show="filterModuleSelected === '{{ $module }}'"
                       xmlns="http://www.w3.org/2000/svg"
                       fill="none"
                       viewBox="0 0 24 24"
                       stroke-width="2"
                       stroke="currentColor"
                       class="size-4 text-blue-600">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          d="m4.5 12.75 6 6 9-13.5" />
                  </svg>
                </li>
              @endforeach
            </ul>
          </div>
        </div>
        <!-- All Status Dropdown -->
        <div x-data="{ open: false, selected: '{{ request('status', 'All Status') }}' }"
             class="relative w-full md:w-auto">
          <input type="hidden"
                 name="status"
                 :value="selected !== 'All Status' ? selected : ''">
          <button @click="open = !open"
                  type="button"
                  class="bg-white w-full md:min-w-[140px] lg:min-w-[150px] border border-gray-200 text-gray-700 text-xs md:text-sm rounded-md py-2 px-3 md:px-4 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center gap-2 whitespace-nowrap">
            <span x-text="selected"
                  class="font-medium"></span>
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="1.5"
                 stroke="currentColor"
                 class="size-4 transition-transform ml-auto"
                 :class="{ 'rotate-180': open }">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
          </button>
          <div x-show="open"
               @click.away="open = false"
               x-cloak
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="opacity-0 scale-95"
               x-transition:enter-end="opacity-100 scale-100"
               x-transition:leave="transition ease-in duration-150"
               x-transition:leave-start="opacity-100 scale-100"
               x-transition:leave-end="opacity-0 scale-95"
               class="absolute z-[99] mt-2 bg-white border border-gray-200 rounded-md shadow-lg overflow-hidden w-full">
            <ul class="py-1">
              <li @click="selected = 'All Status'; open = false"
                  :class="selected === 'All Status' ? 'bg-blue-50' : ''"
                  class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                <span>All Status</span>
                <svg x-show="selected === 'All Status'"
                     xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="2"
                     stroke="currentColor"
                     class="size-4 text-blue-600">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        d="m4.5 12.75 6 6 9-13.5" />
                </svg>
              </li>
              <li @click="selected = 'Pending'; open = false"
                  :class="selected === 'Pending' ? 'bg-blue-50' : ''"
                  class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                <span>Pending</span>
                <svg x-show="selected === 'Pending'"
                     xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="2"
                     stroke="currentColor"
                     class="size-4 text-blue-600">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        d="m4.5 12.75 6 6 9-13.5" />
                </svg>
              </li>
              <li @click="selected = 'Success'; open = false"
                  :class="selected === 'Success' ? 'bg-blue-50' : ''"
                  class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                <span>Success</span>
                <svg x-show="selected === 'Success'"
                     xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="2"
                     stroke="currentColor"
                     class="size-4 text-blue-600">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        d="m4.5 12.75 6 6 9-13.5" />
                </svg>
              </li>
              <li @click="selected = 'Failed'; open = false"
                  :class="selected === 'Failed' ? 'bg-blue-50' : ''"
                  class="px-4 py-2 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer transition font-medium flex items-center justify-between">
                <span>Failed</span>
                <svg x-show="selected === 'Failed'"
                     xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="2"
                     stroke="currentColor"
                     class="size-4 text-blue-600">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        d="m4.5 12.75 6 6 9-13.5" />
                </svg>
              </li>
            </ul>
          </div>
        </div>

        <!-- Jenis Transaksi Dropdown (Hanya untuk Journal Voucher) -->
        <div x-show="filterModuleSelected === 'Journal Voucher'" x-cloak class="relative w-full md:w-auto">
          <select name="jenis_transaksi"
                  class="bg-white w-full md:min-w-[180px] border border-gray-200 text-gray-700 text-xs md:text-sm rounded-md py-2 px-3 md:px-4 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
            <option value="">Semua Jenis Transaksi</option>
            @foreach ($transactionTypeOptions ?? [] as $type)
              <option value="{{ $type }}" {{ request('jenis_transaksi') === $type ? 'selected' : '' }}>
                {{ $type }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="relative w-full md:w-auto">
          <select name="per_page"
                  class="bg-white w-full md:min-w-[110px] border border-gray-200 text-gray-700 text-xs md:text-sm rounded-md py-2 px-3 md:px-4 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
            @foreach ($perPageOptions ?? [100, 200, 300, 400, 500, 1000, 2000] as $option)
              <option value="{{ $option }}"
                      {{ (int) ($currentPerPage ?? request('per_page', 100)) === (int) $option ? 'selected' : '' }}>
                {{ $option }} / page
              </option>
            @endforeach
          </select>
        </div>

        @php
          $selectedCustomerName = (string) request('customer_name', '');
          $selectedVendorName = (string) request('vendor_name', '');
        @endphp

        <div class="relative w-full md:w-auto">
          <select name="customer_name"
                  class="bg-white w-full md:min-w-[200px] border border-gray-200 text-gray-700 text-xs md:text-sm rounded-md py-2 px-3 md:px-4 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
            <option value=""
                    {{ $selectedCustomerName === '' ? 'selected' : '' }}>Semua Pelanggan</option>
            @foreach ($customerNames ?? [] as $customerName)
              <option value="{{ $customerName }}"
                      {{ (string) $customerName === $selectedCustomerName ? 'selected' : '' }}>
                {{ $customerName }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="relative w-full md:w-auto">
          <select name="vendor_name"
                  class="bg-white w-full md:min-w-[200px] border border-gray-200 text-gray-700 text-xs md:text-sm rounded-md py-2 px-3 md:px-4 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
            <option value=""
                    {{ $selectedVendorName === '' ? 'selected' : '' }}>Semua Pemasok / Vendor</option>
            @foreach ($vendorNames ?? [] as $vendorName)
              <option value="{{ $vendorName }}"
                      {{ (string) $vendorName === $selectedVendorName ? 'selected' : '' }}>
                {{ $vendorName }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="relative w-full md:w-auto">
          <input type="text"
                 name="bank_name"
                 list="bank_names_list"
                 placeholder="Semua Kas / Bank..."
                 value="{{ request('bank_name') }}"
                 class="bg-white rounded-md py-2 px-3 md:px-4 border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs md:text-sm w-full md:w-[200px] font-medium"
                 title="Filter Nama Kas / Bank">
          <datalist id="bank_names_list">
            @foreach ($bankNames ?? [] as $bName)
              <option value="{{ $bName }}">
            @endforeach
          </datalist>
        </div>

        <input type="date"
               name="trans_date_start"
               value="{{ request('trans_date_start') }}"
               class="bg-white rounded-md py-2 px-3 md:px-4 border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs md:text-sm w-full md:w-auto font-medium"
               title="Tgl Mulai Transaksi">

        <input type="date"
               name="trans_date_end"
               value="{{ request('trans_date_end') }}"
               class="bg-white rounded-md py-2 px-3 md:px-4 border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs md:text-sm w-full md:w-auto font-medium"
               title="Tgl Selesai Transaksi">

        <div class="relative w-full md:w-auto">
          <select name="sort_date"
                  class="bg-white w-full md:min-w-[150px] border border-gray-200 text-gray-700 text-xs md:text-sm rounded-md py-2 px-3 md:px-4 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
            <option value="desc"
                    {{ request('sort_date', 'desc') === 'desc' ? 'selected' : '' }}>Tanggal: Terbaru</option>
            <option value="asc"
                    {{ request('sort_date') === 'asc' ? 'selected' : '' }}>Tanggal: Terlama</option>
          </select>
        </div>

        {{-- Duplicate filter toggle --}}
        <label class="flex items-center gap-2 bg-white border border-gray-200 rounded-md py-2 px-3 md:px-4 cursor-pointer hover:border-orange-400 transition w-full md:w-auto select-none
            {{ request()->boolean('only_duplicates') ? 'border-orange-400 bg-orange-50' : '' }}">
          <input type="checkbox"
                 name="only_duplicates"
                 value="1"
                 {{ request()->boolean('only_duplicates') ? 'checked' : '' }}
                 class="w-4 h-4 rounded border-2 border-gray-400 accent-orange-500 cursor-pointer">
          <span class="text-xs md:text-sm font-medium {{ request()->boolean('only_duplicates') ? 'text-orange-700' : 'text-gray-700' }} whitespace-nowrap">
            Duplikat saja
          </span>
        </label>

        {{-- Module-specific detail field search --}}
        @php
          $supportedModulesForDetailSearch = array_keys($moduleDetailSearchConfig);
          $detailSearchHints = [
              'Sales Receipt' => 'Cari invoice no (e.g. INV-001)...',
              'Purchase Payment' => 'Cari invoice no (e.g. PINV-001)...',
              'Sales Return' => 'Cari invoice no referensi...',
              'Sales Invoice' => 'Cari nomor invoice...',
          ];
        @endphp
        <div x-show='@json($supportedModulesForDetailSearch).includes(filterModuleSelected)'
             x-cloak
             class="w-full md:w-auto">
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg xmlns="http://www.w3.org/2000/svg"
                   fill="none"
                   viewBox="0 0 24 24"
                   stroke-width="1.5"
                   stroke="currentColor"
                   class="w-4 h-4 text-indigo-400">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803a7.5 7.5 0 0 0 10.607 0Z" />
              </svg>
            </div>
            <input type="text"
                   name="detail_field_search"
                   value="{{ request('detail_field_search') }}"
                   :placeholder='(@json($detailSearchHints)[filterModuleSelected] ?? "Cari di detail field...")'
                   class="bg-white rounded-md py-2 pl-9 pr-3 md:pr-4 border border-indigo-200 focus:outline-none focus:ring-2 focus:ring-indigo-400 text-xs md:text-sm w-full md:w-[220px] font-medium placeholder:text-gray-400">
          </div>
        </div>

        <button type="button"
                @click="selectAll = true; selectAllTransactions()"
                :disabled="!{{ $transactions->count() }}"
                class="bg-white w-full md:w-auto hover:bg-gray-100 p-2 border border-gray-200 rounded-lg text-xs md:text-sm text-black font-medium cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
          Select All
        </button>
        <button type="button"
                @click="clearAll()"
                class="bg-white w-full md:w-auto hover:bg-gray-100 p-2 border border-gray-200 rounded-lg text-xs md:text-sm text-black font-medium cursor-pointer">
          Clear
        </button>
        <button type="submit"
                class="bg-blue-600 w-full md:w-auto hover:bg-blue-700 p-2 border border-blue-600 rounded-lg text-xs md:text-sm text-white font-semibold cursor-pointer">
          Apply Filter
        </button>
        <a href="{{ route('migrate.index') }}"
           class="bg-white w-full md:w-auto hover:bg-gray-100 p-2 border border-gray-200 rounded-lg text-xs md:text-sm text-gray-700 font-medium cursor-pointer text-center">
          Reset
        </a>
      </form>

      {{-- table --}}
      <div class="w-full overflow-hidden border border-gray-200 rounded-lg">
        <div class="overflow-x-auto max-h-[400px] md:max-h-[500px] lg:max-h-[600px] overflow-y-auto">
          <table class="w-full border-collapse min-w-[800px]">
            <thead class="sticky top-0 z-10">
              <tr class="bg-gray-100 border-b border-gray-200">
                <th class="p-2 md:p-4 text-left">
                  <input type="checkbox"
                         x-model="selectAll"
                         @change="selectAllTransactions()"
                         class="w-5 h-5 rounded border-2 border-gray-400 text-blue-600 bg-white accent-blue-600 focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 cursor-pointer">
                </th>
                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">
                  Nomor Lama</th>
                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">
                  Nama</th>
                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">
                  Nomor Baru</th>
                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">DB Sumber</th>
                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">Modul</th>
                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">Tgl Transaksi</th>
                <th class="p-2 md:p-4 text-center text-xs md:text-sm font-semibold text-gray-700">
                  Status</th>
                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">
                  Detail Error</th>
                <th class="p-2 md:p-4 text-center text-xs md:text-sm font-semibold text-gray-700">
                  Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white">
              @forelse ($transactions as $transaction)
                @php $isDuplicate = isset($duplicateTransactionNos[$transaction->transaction_no]); @endphp
                <tr class="border-b border-gray-100 transition {{ $isDuplicate ? 'bg-orange-50 hover:bg-orange-100' : 'hover:bg-gray-50' }}">
                  <td class="p-2 md:p-4">
                    <input type="checkbox"
                           x-model="selected"
                           value="{{ (string) $transaction->id }}"
                           class="w-5 h-5 rounded border-2 border-gray-400 bg-white accent-blue-600 focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 cursor-pointer">
                  </td>
                  <td class="p-2 md:p-4 text-xs md:text-sm font-medium text-gray-900">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span>{{ $transaction->transaction_no }}</span>
                      @if ($isDuplicate)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-orange-100 text-orange-700 border border-orange-300 whitespace-nowrap">
                          DUPLIKAT
                        </span>
                      @endif
                    </div>
                  </td>
                  <td class="p-2 md:p-4 text-xs md:text-sm text-gray-800 font-medium">
                    {{ $transaction->entity_name ?? '-' }}
                  </td>
                  <td class="p-2 md:p-4 text-xs md:text-sm text-green-700 font-medium">
                    {{ $transaction->new_number ?? '-' }}
                  </td>
                  <td class="p-2 md:p-4 text-xs md:text-sm text-gray-600">
                    {{ $transaction->accurateDatabase?->db_name ?? 'N/A' }}</td>
                  <td class="p-2 md:p-4 text-xs md:text-sm text-gray-600">
                    {{ $transaction->module?->name ?? 'N/A' }}</td>
                  <td class="p-2 md:p-4 text-xs md:text-sm text-gray-600">
                    @php
                      $transDate = $transaction->trans_date_raw ?? null;
                      $transDateDisplay = null;

                      if (!empty($transDate)) {
                          try {
                              $transDateDisplay = \Carbon\Carbon::createFromFormat('d/m/Y', $transDate)->format('d/m/Y');
                          } catch (\Throwable $e) {
                              try {
                                  $transDateDisplay = \Carbon\Carbon::parse($transDate)->format('d/m/Y');
                              } catch (\Throwable $e) {
                                  $transDateDisplay = null;
                              }
                          }
                      }
                    @endphp
                    @if ($transDateDisplay)
                      {{ $transDateDisplay }}
                    @else
                      <span class="text-gray-400">-</span>
                    @endif
                  </td>
                  <td class="p-2 md:p-4 text-center">
                    @if ($transaction->status === 'success')
                      <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke-width="2"
                             stroke="currentColor"
                             class="size-3">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        Success
                      </span>
                    @elseif ($transaction->status === 'pending')
                      <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke-width="2"
                             stroke="currentColor"
                             class="size-3">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Pending
                      </span>
                    @else
                      <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke-width="2"
                             stroke="currentColor"
                             class="size-3">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        Failed
                      </span>
                    @endif
                  </td>
                  <td class="p-2 md:p-4 text-xs md:text-sm text-gray-600">
                    @if ($transaction->status === 'failed' && $transaction->error_message)
                      <div x-data="{ showError: false }"
                           class="relative">
                        <button @click="showError = !showError"
                                type="button"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 text-xs font-medium transition">
                          <svg xmlns="http://www.w3.org/2000/svg"
                               fill="none"
                               viewBox="0 0 24 24"
                               stroke-width="1.5"
                               stroke="currentColor"
                               class="w-4 h-4">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                          </svg>
                          View Error
                        </button>

                        <!-- Error Detail Modal -->
                        <div x-show="showError"
                             @click.away="showError = false"
                             x-cloak
                             class="fixed inset-0 z-50 overflow-y-auto"
                             aria-labelledby="error-modal"
                             role="dialog"
                             aria-modal="true">
                          <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                            <!-- Background overlay -->
                            <div x-show="showError"
                                 x-transition:enter="ease-out duration-300"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 x-transition:leave="ease-in duration-200"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                                 aria-hidden="true"></div>

                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen"
                                  aria-hidden="true">&#8203;</span>

                            <div x-show="showError"
                                 x-transition:enter="ease-out duration-300"
                                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                                 x-transition:leave="ease-in duration-200"
                                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                                 class="inline-block align-bottom bg-white rounded-2xl px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6">

                              <div class="flex items-start gap-4">
                                <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                                  <svg class="h-6 w-6 text-red-600"
                                       xmlns="http://www.w3.org/2000/svg"
                                       fill="none"
                                       viewBox="0 0 24 24"
                                       stroke-width="1.5"
                                       stroke="currentColor">
                                    <path stroke-linecap="round"
                                          stroke-linejoin="round"
                                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                  </svg>
                                </div>
                                <div class="flex-1">
                                  <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                    Error Details - {{ $transaction->transaction_no }}
                                  </h3>
                                  <div class="mt-3 bg-red-50 border border-red-200 rounded-lg p-4">
                                    <p class="text-sm text-red-800 font-mono whitespace-pre-wrap break-words">{{ $transaction->error_message }}</p>
                                  </div>
                                  <div class="mt-4 flex justify-end">
                                    <button @click="showError = false"
                                            type="button"
                                            class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition">
                                      Close
                                    </button>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    @else
                      <span class="text-gray-400 text-xs">-</span>
                    @endif
                  </td>
                  <td class="p-2 md:p-4 text-center">
                    <div class="flex items-center justify-center gap-2">
                      @if ($transaction->status === 'success')
                        <!-- Create Again Button -->
                        <button @click.prevent="openMigrateModal({{ $transaction->id }})"
                                type="button"
                                :disabled="migrating"
                                class="inline-flex items-center justify-center p-1.5 md:p-2 rounded-lg transition"
                                :class="migrating ? 'opacity-50 cursor-not-allowed text-gray-400' : 'hover:bg-green-50 text-green-600 hover:text-green-700'"
                                title="Create Again (Force Create)">
                          <svg xmlns="http://www.w3.org/2000/svg"
                               fill="none"
                               viewBox="0 0 24 24"
                               stroke-width="1.5"
                               stroke="currentColor"
                               class="w-4 h-4 md:w-5 md:h-5">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  d="M16.023 9.348h4.992V4.356m-.944 13.012a9 9 0 10-2.121 2.121M2.977 14.652H7.97v4.992" />
                          </svg>
                        </button>
                      @endif
                      <!-- Edit Button -->
                      <button @click="openEditModal({{ $transaction->id }}, '{{ $transaction->transaction_no }}')"
                              class="inline-flex items-center justify-center p-1.5 md:p-2 rounded-lg hover:bg-blue-50 text-blue-600 hover:text-blue-700 transition"
                              title="Edit JSON Data">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke-width="1.5"
                             stroke="currentColor"
                             class="w-4 h-4 md:w-5 md:h-5">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                      </button>
                      <!-- Delete Button -->
                      <button @click="confirmSingleDelete({{ $transaction->id }})"
                              class="inline-flex items-center justify-center p-1.5 md:p-2 rounded-lg hover:bg-red-50 text-red-600 hover:text-red-700 transition"
                              title="Delete Transaction">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             fill="none"
                             viewBox="0 0 24 24"
                             stroke-width="1.5"
                             stroke="currentColor"
                             class="w-4 h-4 md:w-5 md:h-5">
                          <path stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="10"
                      class="p-8 text-center text-gray-500">
                    <div class="flex flex-col items-center gap-2">
                      <svg xmlns="http://www.w3.org/2000/svg"
                           fill="none"
                           viewBox="0 0 24 24"
                           stroke-width="1.5"
                           stroke="currentColor"
                           class="w-12 h-12 text-gray-400">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                      </svg>
                      <p class="text-sm font-medium">Tidak ada transaksi ditemukan</p>
                    </div>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
          {{ $transactions->links() }}
        </div>
      </div>

      <!-- Single Delete Confirmation Modal -->
      <div x-show="showSingleDeleteModal"
           x-cloak
           class="fixed inset-0 z-50 overflow-y-auto"
           aria-labelledby="modal-title"
           role="dialog"
           aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4">
          <!-- Background overlay -->
          <div x-show="showSingleDeleteModal"
               x-transition:enter="ease-out duration-300"
               x-transition:enter-start="opacity-0"
               x-transition:enter-end="opacity-100"
               x-transition:leave="ease-in duration-200"
               x-transition:leave-start="opacity-100"
               x-transition:leave-end="opacity-0"
               class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
               @click="showSingleDeleteModal = false; deleteTarget = null"></div>

          <!-- Modal panel -->
          <div x-show="showSingleDeleteModal"
               x-transition:enter="ease-out duration-300"
               x-transition:enter-start="opacity-0 scale-95"
               x-transition:enter-end="opacity-100 scale-100"
               x-transition:leave="ease-in duration-200"
               x-transition:leave-start="opacity-100 scale-100"
               x-transition:leave-end="opacity-0 scale-95"
               class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-lg">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <div class="sm:flex sm:items-start">
                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                  <svg class="h-6 w-6 text-red-600"
                       xmlns="http://www.w3.org/2000/svg"
                       fill="none"
                       viewBox="0 0 24 24"
                       stroke-width="2"
                       stroke="currentColor">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                  </svg>
                </div>
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                  <h3 class="text-lg leading-6 font-medium text-gray-900"
                      id="modal-title">
                    Hapus Transaksi
                  </h3>
                  <div class="mt-2">
                    <p class="text-sm text-gray-500">
                      Apakah Anda yakin ingin menghapus transaksi ini? Tindakan ini tidak dapat dibatalkan.
                    </p>
                  </div>
                </div>
              </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
              <form x-ref="singleDeleteForm"
                    :action="`/migrate/${deleteTarget}`"
                    method="POST"
                    class="inline">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                  Hapus
                </button>
              </form>
              <button type="button"
                      @click="showSingleDeleteModal = false; deleteTarget = null"
                      class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                Batal
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit JSON Modal -->
      <div x-show="showEditModal"
           x-cloak
           class="fixed inset-0 z-50 overflow-y-auto"
           aria-labelledby="edit-modal-title"
           role="dialog"
           aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
          <!-- Background overlay -->
          <div x-show="showEditModal"
               x-transition:enter="ease-out duration-300"
               x-transition:enter-start="opacity-0"
               x-transition:enter-end="opacity-100"
               x-transition:leave="ease-in duration-200"
               x-transition:leave-start="opacity-100"
               x-transition:leave-end="opacity-0"
               class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
               @click="closeEditModal()"></div>

          <span class="hidden sm:inline-block sm:align-middle sm:h-screen"
                aria-hidden="true">&#8203;</span>

          <!-- Modal panel -->
          <div x-show="showEditModal"
               x-transition:enter="ease-out duration-300"
               x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
               x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
               x-transition:leave="ease-in duration-200"
               x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
               x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
               class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">

            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="flex items-center justify-center w-10 h-10 rounded-full bg-white/20">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         fill="none"
                         viewBox="0 0 24 24"
                         stroke-width="1.5"
                         stroke="currentColor"
                         class="w-6 h-6 text-white">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
                    </svg>
                  </div>
                  <div>
                    <h3 class="text-lg font-semibold text-white"
                        id="edit-modal-title">
                      Edit JSON Data
                    </h3>
                    <p class="text-sm text-blue-100"
                       x-text="editTransaction ? `Transaction: ${editTransaction.no}` : ''"></p>
                  </div>
                </div>
                <button @click="closeEditModal()"
                        type="button"
                        class="text-white hover:text-gray-200 transition">
                  <svg xmlns="http://www.w3.org/2000/svg"
                       fill="none"
                       viewBox="0 0 24 24"
                       stroke-width="2"
                       stroke="currentColor"
                       class="w-6 h-6">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M6 18 18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Modal Body -->
            <div class="bg-white px-6 py-5">
              <!-- Mode Toggle -->
              <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <span class="text-sm font-medium text-gray-700">Edit Mode:</span>
                  <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1">
                    <button @click="switchToForm()"
                            :class="editMode === 'form' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 flex items-center gap-2">
                      <svg xmlns="http://www.w3.org/2000/svg"
                           fill="none"
                           viewBox="0 0 24 24"
                           stroke-width="1.5"
                           stroke="currentColor"
                           class="w-4 h-4">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                      </svg>
                      Form View
                    </button>
                    <button @click="switchToJson()"
                            :class="editMode === 'json' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 flex items-center gap-2">
                      <svg xmlns="http://www.w3.org/2000/svg"
                           fill="none"
                           viewBox="0 0 24 24"
                           stroke-width="1.5"
                           stroke="currentColor"
                           class="w-4 h-4">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
                      </svg>
                      JSON View
                    </button>
                  </div>
                </div>
                <span class="text-xs text-gray-500 bg-blue-50 px-3 py-1 rounded-full">
                  <span x-show="editMode === 'form'">📝 User-friendly form mode</span>
                  <span x-show="editMode === 'json'">💻 Advanced JSON mode</span>
                </span>
              </div>

              <!-- Form Mode -->
              <div x-show="editMode === 'form'"
                   class="space-y-4">
                <!-- Search Field -->
                <div class="sticky top-0 bg-white z-10 pb-3">
                  <div class="relative">
                    <input type="text"
                           x-model="searchField"
                           placeholder="Search fields..."
                           class="w-full px-4 py-2 pl-10 pr-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         fill="none"
                         viewBox="0 0 24 24"
                         stroke-width="1.5"
                         stroke="currentColor"
                         class="w-5 h-5 text-gray-400 absolute left-3 top-2.5">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                  </div>
                  <p class="text-xs text-gray-500 mt-1"
                     x-show="searchField"
                     x-text="`Found ${filteredFields().length} field(s)`"></p>
                </div>

                <!-- Form Fields -->
                <div class="max-h-[500px] overflow-y-auto space-y-3 pr-2">
                  <template x-for="(field, index) in filteredFields()"
                            :key="field.key">
                    <div class="border border-gray-200 rounded-lg p-3 hover:border-blue-300 transition"
                         :class="field.type === 'object' && 'bg-gray-50'">
                      <!-- Object Label -->
                      <template x-if="field.type === 'object'">
                        <div class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                          <svg xmlns="http://www.w3.org/2000/svg"
                               fill="none"
                               viewBox="0 0 24 24"
                               stroke-width="1.5"
                               stroke="currentColor"
                               class="w-4 h-4 text-blue-500">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776" />
                          </svg>
                          <span x-text="field.displayKey"></span>
                        </div>
                      </template>

                      <!-- Regular Field -->
                      <template x-if="field.type !== 'object'">
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-1.5"
                                 x-text="field.key"></label>

                          <!-- Array Field -->
                          <template x-if="field.type === 'array'">
                            <textarea :value="field.value"
                                      @input="updateValue(field.key, $event.target.value)"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-mono"
                                      rows="3"></textarea>
                          </template>

                          <!-- Number Field -->
                          <template x-if="field.type === 'number'">
                            <input type="number"
                                   :value="field.value"
                                   @input="updateValue(field.key, parseFloat($event.target.value) || 0)"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                          </template>

                          <!-- Boolean Field -->
                          <template x-if="field.type === 'boolean'">
                            <select :value="field.value"
                                    @change="updateValue(field.key, $event.target.value === 'true')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                              <option value="true">true</option>
                              <option value="false">false</option>
                            </select>
                          </template>

                          <!-- Null Field -->
                          <template x-if="field.type === 'null'">
                            <div class="flex gap-2">
                              <input type="text"
                                     :value="field.value || ''"
                                     @input="updateValue(field.key, $event.target.value || null)"
                                     placeholder="null (enter value to change)"
                                     class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                              <button @click="updateValue(field.key, null); $event.target.previousElementSibling.value = ''"
                                      type="button"
                                      class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-md transition"
                                      title="Set to null">
                                Set NULL
                              </button>
                            </div>
                          </template>

                          <!-- String/Default Field -->
                          <template x-if="field.type !== 'array' && field.type !== 'number' && field.type !== 'boolean' && field.type !== 'null'">
                            <input type="text"
                                   :value="field.value"
                                   @input="updateValue(field.key, $event.target.value)"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                          </template>

                          <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs px-2 py-0.5 rounded-full"
                                  :class="{
                                      'bg-blue-100 text-blue-700': field.type === 'string',
                                      'bg-green-100 text-green-700': field.type === 'number',
                                      'bg-purple-100 text-purple-700': field.type === 'boolean',
                                      'bg-orange-100 text-orange-700': field.type === 'array',
                                      'bg-gray-100 text-gray-700': field.type === 'null'
                                  }"
                                  x-text="field.type"></span>
                          </div>
                        </div>
                      </template>
                    </div>
                  </template>

                  <div x-show="filteredFields().length === 0"
                       class="text-center py-8 text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         fill="none"
                         viewBox="0 0 24 24"
                         stroke-width="1.5"
                         stroke="currentColor"
                         class="w-12 h-12 mx-auto mb-2 text-gray-400">
                      <path stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <p class="text-sm">No fields found matching your search</p>
                  </div>
                </div>
              </div>

              <!-- JSON Mode -->
              <div x-show="editMode === 'json'">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  JSON Data
                  <span class="text-gray-500 font-normal">(Advanced editing mode)</span>
                </label>
                <div class="relative">
                  <textarea x-model="editData"
                            class="w-full h-[500px] px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm resize-none"
                            style="tab-size: 2;"
                            placeholder='{ "key": "value" }'></textarea>
                  <div class="absolute top-2 right-2 flex gap-2">
                    <button @click="formatEditJson()"
                            type="button"
                            class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-md transition flex items-center gap-1.5"
                            title="Format JSON">
                      <svg xmlns="http://www.w3.org/2000/svg"
                           fill="none"
                           viewBox="0 0 24 24"
                           stroke-width="1.5"
                           stroke="currentColor"
                           class="w-4 h-4">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                      </svg>
                      Format
                    </button>
                  </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                  <strong>Tip:</strong> Click "Format" to beautify your JSON. Switch to Form View for easier editing.
                </p>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-3">
              <button @click="saveEdit()"
                      type="button"
                      class="w-full sm:w-auto inline-flex justify-center items-center gap-2 rounded-lg border border-transparent shadow-sm px-5 py-2.5 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
                <svg xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="1.5"
                     stroke="currentColor"
                     class="w-5 h-5">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Save Changes
              </button>
              <button @click="closeEditModal()"
                      type="button"
                      class="w-full sm:w-auto inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-5 py-2.5 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition">
                Cancel
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Hidden form for edit -->
      <form x-ref="editForm"
            :action="editTransaction ? `/migrate/${editTransaction.id}` : '#'"
            method="POST"
            class="hidden">
        @csrf
        @method('PUT')
        <input type="hidden"
               name="data"
               id="editDataInput">
      </form>

      <!-- Hidden form for bulk delete -->
      <form x-ref="bulkDeleteForm"
            action="{{ route('migrate.destroyMultiple') }}"
            method="POST"
            class="hidden">
        @csrf
        @method('DELETE')
        <template x-for="id in selected"
                  :key="id">
          <input type="hidden"
                 name="ids[]"
                 :value="id">
        </template>
      </form>

      
      <!-- Target DB Selection Modal -->
      <div x-show="showTargetDbModal" class="fixed inset-0 z-[100] overflow-y-auto" style="display: none;" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
          <div x-show="showTargetDbModal" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true"></div>
          <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
          <div x-show="showTargetDbModal" 
               x-transition:enter="ease-out duration-300" 
               x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
               x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
               x-transition:leave="ease-in duration-200" 
               x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
               x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
               class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-5xl lg:max-w-6xl w-full border border-gray-100">
            
            <div class="px-6 py-5 bg-white border-b border-gray-100">
              <h3 class="text-xl font-bold text-gray-900" id="modal-title">Konfirmasi Migrasi Data</h3>
              <p class="mt-1 text-sm text-gray-500">Silakan lengkapi pengaturan di bawah ini sebelum memulai proses migrasi ke Accurate.</p>
            </div>

            <div class="px-6 py-6 bg-gray-50/30">
              <div class="space-y-5">
                
                <!-- Migration Info Summary -->
                <div class="grid grid-cols-2 gap-4">
                  <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col items-center text-center">
                    <span class="text-xs font-bold tracking-wider text-gray-500 uppercase mb-1">Total Data</span>
                    <span class="text-2xl font-black text-blue-600" x-text="modalTargetIds.length"></span>
                  </div>
                  <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col items-center text-center">
                    <span class="text-xs font-bold tracking-wider text-gray-500 uppercase mb-1">Filter Modul</span>
                    <span class="text-sm font-bold text-indigo-600 mt-1" x-text="filterModuleSelected || 'All Modules'"></span>
                  </div>
                </div>

                <!-- Database Selection -->
                <div>
                  <label class="block text-sm font-bold text-gray-700 mb-2">Pilih Target Database <span class="text-red-500">*</span></label>
                  <select x-model="modalSelectedDbId" @change="fetchPreviewData()" class="block w-full border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm bg-white cursor-pointer py-2.5">
                    <option value="">-- Pilih Database Accurate --</option>
                    @foreach ($databases as $db)
                      <option value="{{ $db['id'] }}">{{ $db['alias'] }} ({{ $db['id'] }})</option>
                    @endforeach
                  </select>
                </div>

                <!-- Preview List -->
                <div x-show="modalSelectedDbId" x-transition class="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm mt-4 w-full">
                  <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <span class="text-sm font-bold text-gray-700">Preview Transaksi (<span x-text="modalPreviewData.length"></span>)</span>
                    <div x-show="modalPreviewLoading" class="text-xs text-blue-600 font-medium flex items-center gap-1.5">
                      <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                      Loading...
                    </div>
                  </div>
                  <div class="max-h-72 overflow-y-auto w-full">
                    <table class="w-full min-w-full divide-y divide-gray-200 border-collapse table-fixed">
                      <thead class="bg-gray-50 sticky top-0 z-10 shadow-2xs">
                        <tr>
                          <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 bg-gray-50 w-4/12">Nomor Lama</th>
                          <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 bg-gray-50 w-2/12">Modul</th>
                          <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 bg-gray-50 w-2/12">Tanggal</th>
                          <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 bg-gray-50 w-4/12">Nomor Baru (Target)</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100 bg-white">
                        <template x-for="item in modalPreviewData" :key="item.id">
                          <tr class="hover:bg-gray-50/80 transition-colors border-t border-gray-100">
                            <td class="px-4 py-2.5 text-xs font-mono font-bold text-gray-900 break-all w-4/12" x-text="item.old_number"></td>
                            <td class="px-4 py-2.5 text-xs text-gray-600 w-2/12" x-text="item.module_name"></td>
                            <td class="px-4 py-2.5 text-xs text-gray-600 whitespace-nowrap w-2/12" x-text="item.trans_date"></td>
                            <td class="px-4 py-1.5 w-4/12">
                              <input type="text" :disabled="modalNumberingMode !== 'custom'" x-model="modalTargetNumbers[item.id]" class="w-full text-xs font-mono border-gray-300 rounded shadow-2xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3 disabled:bg-gray-50 disabled:text-gray-500" :placeholder="modalNumberingMode === 'original' ? item.old_number : 'Auto'">
                            </td>
                          </tr>
                          <tr x-show="item.detail_invoices && item.detail_invoices.length > 0" class="bg-blue-50/30">
                            <td colspan="4" class="px-4 py-3 text-xs w-full">
                              <div class="border border-blue-200 rounded-xl p-3.5 bg-blue-50/80 space-y-2.5 shadow-2xs w-full">
                                <div class="flex items-center justify-between font-bold text-blue-950 text-xs">
                                  <span class="flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-600">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    Mapping Manual Faktur Terkait (Detail Invoice)
                                  </span>
                                  <span class="text-xs text-blue-700 font-medium">Edit jika nomor faktur di target berbeda</span>
                                </div>
                                <div class="space-y-2 w-full">
                                  <template x-for="inv in item.detail_invoices" :key="inv.old_number">
                                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 bg-white p-2.5 rounded-lg border border-blue-200 shadow-2xs w-full">
                                      <div class="sm:w-1/2 flex items-center justify-between text-xs px-1">
                                        <span class="text-gray-500 font-medium">Faktur Sumber:</span>
                                        <span class="font-mono font-bold text-gray-900 text-xs" x-text="inv.old_number"></span>
                                      </div>
                                      <span class="hidden sm:inline text-blue-500 font-bold text-sm px-1">➔</span>
                                      <div class="sm:w-1/2 w-full">
                                        <input type="text"
                                               x-model="modalCustomInvoiceMappings[item.id][inv.old_number]"
                                               class="w-full text-xs font-mono border-blue-300 rounded-md shadow-2xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3 bg-white text-blue-900 font-bold"
                                               placeholder="Nomor Faktur Target">
                                      </div>
                                    </div>
                                  </template>
                                </div>
                              </div>
                            </td>
                          </tr>
                        </template>
                        <tr x-show="!modalPreviewLoading && modalPreviewData.length === 0">
                          <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">Pilih database untuk melihat preview data.</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>


                <!-- Numbering Mode Options -->
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm space-y-3">
                  <span class="block text-sm font-bold text-gray-900">Opsi Penomoran Transaksi</span>
                  
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <!-- Option 1: Gunakan Nomor Lama -->
                    <label class="flex items-start p-3 border border-gray-200 rounded-lg hover:bg-gray-50/50 cursor-pointer transition-colors" :class="modalNumberingMode === 'original' ? 'border-blue-500 bg-blue-50/10' : ''">
                      <div class="flex items-center h-5 mt-0.5">
                        <input type="radio" name="numbering_mode" value="original" x-model="modalNumberingMode" @change="handleNumberingModeChange()" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500 bg-white">
                      </div>
                      <div class="ml-2.5">
                        <span class="block text-xs font-bold text-gray-900">Nomor Asli (Lama)</span>
                        <span class="block text-[10px] text-gray-500 mt-0.5 leading-normal">Menggunakan nomor asli dari transaksi sumber (nomor lama).</span>
                      </div>
                    </label>

                    <!-- Option 2: Custom Auto-Numbering -->
                    <label class="flex items-start p-3 border border-gray-200 rounded-lg hover:bg-gray-50/50 cursor-pointer transition-colors" :class="modalNumberingMode === 'custom' ? 'border-blue-500 bg-blue-50/10' : ''">
                      <div class="flex items-center h-5 mt-0.5">
                        <input type="radio" name="numbering_mode" value="custom" x-model="modalNumberingMode" @change="handleNumberingModeChange()" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500 bg-white">
                      </div>
                      <div class="ml-2.5">
                        <span class="block text-xs font-bold text-gray-900">Nomor Kustom (Custom)</span>
                        <span class="block text-[10px] text-gray-500 mt-0.5 leading-normal">Nomor custom kronologis (SI.2026.07.12.001).</span>
                      </div>
                    </label>
                  </div>
                </div>

                <!-- JU Suffix Option (Only shown if module is Journal Voucher) -->
                <label x-show="!filterModuleSelected || filterModuleSelected === 'All Modules' || filterModuleSelected === 'Journal Voucher'" for="add_ju_suffix" class="flex items-start bg-white hover:bg-blue-50/30 p-4 rounded-xl border border-gray-200 cursor-pointer transition-colors shadow-sm">
                  <div class="flex items-center h-5 mt-0.5">
                    <input id="add_ju_suffix" type="checkbox" x-model="modalAddJuSuffix" class="w-4 h-4 text-blue-500 bg-white border-gray-300 rounded focus:ring-blue-500">
                  </div>
                  <div class="ml-3">
                    <span class="block text-sm font-bold text-gray-900">Tambahkan Suffix -JU (Journal Voucher)</span>
                    <span class="block text-xs text-gray-500 mt-1 leading-relaxed">Khusus untuk modul Journal Voucher yang berstatus gagal, sistem akan menambahkan -JU pada nomor transaksi agar bisa dicoba ulang ke Accurate tanpa conflict duplikat.</span>
                  </div>
                </label>

              </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 sm:flex sm:flex-row-reverse gap-3">
              <button @click="executeMigration()" type="button" :disabled="!modalSelectedDbId" class="inline-flex justify-center w-full px-6 py-2.5 text-sm font-bold text-white bg-blue-600 border border-transparent rounded-xl shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed transition-all active:scale-95">Mulai Migrasi</button>
              <button @click="closeMigrateModal()" type="button" class="mt-3 sm:mt-0 inline-flex justify-center w-full px-6 py-2.5 text-sm font-bold text-gray-700 bg-white border border-gray-300 rounded-xl shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-200 sm:w-auto transition-all active:scale-95">Batal</button>
            </div>
          </div>
        </div>
      </div>
      <!-- Warning Modal -->
      <div x-show="showWarningModal" class="fixed inset-0 z-[110] overflow-y-auto" style="display: none;" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
          <div x-show="showWarningModal" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true"></div>
          <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
          <div x-show="showWarningModal" 
               x-transition:enter="ease-out duration-300" 
               x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
               x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
               x-transition:leave="ease-in duration-200" 
               x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
               x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
               class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100">
            
            <div class="bg-white px-6 pt-6 pb-6">
              <div class="sm:flex sm:items-start">
                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-14 w-14 rounded-full bg-yellow-100 sm:mx-0 sm:h-12 sm:w-12">
                  <svg class="h-7 w-7 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                </div>
                <div class="mt-4 text-center sm:mt-0 sm:ml-5 sm:text-left">
                  <h3 class="text-xl font-bold text-gray-900" id="modal-title">
                    Peringatan Modul
                  </h3>
                  <div class="mt-2 space-y-2">
                    <p class="text-sm text-gray-600 leading-relaxed">
                      Anda memilih transaksi Faktur atau Pembayaran. Harap diperhatikan:
                    </p>
                    <p class="text-sm font-bold text-red-600 leading-relaxed">
                      JANGAN LUPA UNTUK DAHULUKAN MIGRATE JURNAL UMUM (Bila Ada).
                    </p>
                    <p class="text-sm text-gray-600 leading-relaxed pt-2">
                      Apakah Anda yakin ingin melanjutkan migrasi sekarang?
                    </p>
                  </div>
                </div>
              </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 sm:flex sm:flex-row-reverse gap-3">
              <button @click="showWarningModal = false; openMigrateModal(pendingMigrateSingleId, true)" type="button" class="inline-flex justify-center w-full px-6 py-2.5 text-sm font-bold text-white bg-yellow-600 border border-transparent rounded-xl shadow-sm hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 sm:w-auto transition-all active:scale-95">Lanjutkan Migrate</button>
              <button @click="showWarningModal = false; pendingMigrateSingleId = null" type="button" class="mt-3 sm:mt-0 inline-flex justify-center w-full px-6 py-2.5 text-sm font-bold text-gray-700 bg-white border border-gray-300 rounded-xl shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-200 sm:w-auto transition-all active:scale-95">Batal</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Hidden form for bulk migrate -->
      <form x-ref="migrateForm"
            action="{{ route('migrate.toAccurate') }}"
            method="POST"
            class="hidden">
        @csrf
        <input type="hidden"
               name="target_database_id"
               id="targetDbIdInput">
        <template x-for="id in selected"
                  :key="id">
          <input type="hidden"
                 name="ids[]"
                 :value="id">
        </template>
      </form>
    </div>
  </div>
</x-app-layout>
