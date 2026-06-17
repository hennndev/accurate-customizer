<div x-data="globalJobMonitor()" x-init="initMonitor()" 
     class="fixed bottom-6 left-6 z-50 w-80 max-w-[calc(100vw-3rem)] pointer-events-none"
     style="display: none;" 
     x-show="monitorVisible"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
    
    <div class="bg-white/90 backdrop-blur-xl border border-gray-100/50 shadow-2xl rounded-2xl p-5 flex flex-col gap-3 pointer-events-auto ring-1 ring-black/5">
        
        <div class="flex items-center justify-between">
            <div class="flex flex-col">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-0.5">Background Task</span>
                <h3 class="text-sm font-bold text-gray-900 leading-tight" x-text="currentModule"></h3>
            </div>
            <span class="text-[10px] font-bold px-2 py-1 rounded-md tracking-wide uppercase shadow-sm"
                  :class="monitorStatus === 'failed' ? 'bg-red-50 text-red-600 border border-red-100' : (['success','warning','info'].includes(monitorStatus) ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-blue-50 text-blue-600 border border-blue-100')"
                  x-text="monitorStatus === 'running' ? 'Processing' : monitorStatus">
            </span>
        </div>

        <p class="text-xs text-gray-600 font-medium bg-gray-50/50 p-2 rounded-lg" x-text="monitorMessage"></p>

        <div class="flex flex-col gap-1.5 mt-1">
            <div class="flex justify-between items-center text-xs font-bold">
                <span class="text-gray-500">Progress</span>
                <span class="text-blue-600" x-text="`${Math.round(progress)}%`"></span>
            </div>
            <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden shadow-inner">
                <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full transition-all duration-300 relative overflow-hidden"
                     :style="`width: ${progress}%`">
                     <div class="absolute inset-0 bg-white/20 w-full h-full animate-[shimmer_2s_infinite]"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 mt-2">
            <div class="flex flex-col items-center justify-center bg-emerald-50/50 rounded-xl py-2 px-1 border border-emerald-50">
                <span class="text-[10px] text-emerald-600 font-semibold uppercase">Saved</span>
                <span class="text-sm font-bold text-emerald-700" x-text="monitorSaved"></span>
            </div>
            <div class="flex flex-col items-center justify-center bg-rose-50/50 rounded-xl py-2 px-1 border border-rose-50">
                <span class="text-[10px] text-rose-600 font-semibold uppercase">Failed</span>
                <span class="text-sm font-bold text-rose-700" x-text="monitorFailed"></span>
            </div>
        </div>

        <button @click="cancelMonitor()"
                x-show="capturing"
                class="mt-2 w-full px-4 py-2 text-xs font-bold text-red-600 bg-red-50 hover:bg-red-100 rounded-xl transition-colors flex items-center justify-center gap-1.5 border border-red-100/50">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Cancel Process
        </button>
    </div>
</div>

<style>
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
</style>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('globalJobMonitor', () => ({
        monitorVisible: false,
        capturing: false,
        progress: 0,
        currentModule: 'Background Job',
        monitorId: null,
        monitorStatus: 'idle',
        monitorMessage: '',
        monitorSaved: 0,
        monitorFailed: 0,
        monitorItems: 0,
        monitorPages: 0,
        pollingInterval: null,

        initMonitor() {
            this.restoreMonitorFromStorage();

            window.addEventListener('job-started', (e) => {
                if (e.detail && e.detail.monitorId) {
                    this.monitorId = e.detail.monitorId;
                    this.currentModule = e.detail.currentModule || 'Background Job';
                    this.monitorVisible = true;
                    this.capturing = true;
                    this.progress = 0;
                    this.monitorSaved = 0;
                    this.monitorFailed = 0;
                    this.monitorItems = 0;
                    this.monitorPages = 0;
                    this.monitorStatus = 'running';
                    this.monitorMessage = 'Starting job...';
                    this.saveMonitorToStorage();
                    this.resumePolling();
                }
            });
            
            setInterval(() => {
                if (!this.capturing) {
                    this.restoreActiveMonitorFromServer();
                }
            }, 5000);
        },

        saveMonitorToStorage() {
            localStorage.setItem('globalActiveMonitorId', JSON.stringify({
                monitorId: this.monitorId,
                currentModule: this.currentModule,
                progress: this.progress,
                monitorStatus: this.monitorStatus
            }));
        },

        clearMonitorFromStorage() {
            localStorage.removeItem('globalActiveMonitorId');
            if (this.pollingInterval) {
                clearTimeout(this.pollingInterval);
                this.pollingInterval = null;
            }
        },

        restoreMonitorFromStorage() {
            const saved = localStorage.getItem('globalActiveMonitorId');
            if (saved) {
                try {
                    const data = JSON.parse(saved);
                    if (data.monitorId) {
                        this.monitorId = data.monitorId;
                        this.currentModule = data.currentModule;
                        this.progress = data.progress;
                        this.monitorStatus = data.monitorStatus;
                        this.monitorVisible = true;
                        if (['success', 'failed'].includes(data.monitorStatus)) {
                            this.capturing = false;
                        } else {
                            this.capturing = true;
                            this.resumePolling();
                        }
                    }
                } catch (e) {
                    this.clearMonitorFromStorage();
                }
            }
        },

        async restoreActiveMonitorFromServer() {
            if (this.capturing) return false;
            
            try {
                const response = await fetch('/system-logs/active?event_type=capture_queue,migrate_queue,transaction_number_mapping_queue', {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) return false;

                const result = await response.json();
                if (result?.success && result?.active && result?.log?.id) {
                    const log = result.log;
                    if (this.monitorId === log.id) return true;

                    const payload = log?.payload || {};
                    this.monitorId = log.id;
                    this.currentModule = log.module || 'Processing...';
                    this.monitorVisible = true;
                    this.capturing = true;
                    this.monitorStatus = log.status || 'running';
                    this.monitorMessage = log.message || 'Job sedang diproses...';
                    this.progress = Number(payload?.progress || 0);
                    this.monitorSaved = Number(payload?.saved_count || 0);
                    this.monitorFailed = Number(payload?.failed_count || 0);
                    this.monitorItems = Number(payload?.processed_items || 0);
                    this.monitorPages = Number(payload?.processed_pages || 0);
                    this.saveMonitorToStorage();
                    this.resumePolling();
                    return true;
                }
                return false;
            } catch (e) {
                return false;
            }
        },

        async cancelMonitor() {
            if (!this.monitorId) return;
            try {
                const response = await fetch(`/system-logs/${this.monitorId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                });
                const result = await response.json();
                this.monitorStatus = result?.success ? 'failed' : this.monitorStatus;
                this.monitorMessage = result?.message || 'Proses dibatalkan';
                this.capturing = false;
                this.clearMonitorFromStorage();
            } catch (e) {
                console.error(e);
            }
        },

        async resumePolling() {
            if (!this.monitorId) return;
            
            const poll = async () => {
                if (!this.capturing) return;

                try {
                    const response = await fetch(`/system-logs/${this.monitorId}/status`, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    if (response.ok) {
                        const statusResult = await response.json();
                        const payload = statusResult?.payload || {};
                        const trackerStatus = statusResult?.status || 'running';

                        this.currentModule = statusResult?.module || this.currentModule;
                        this.progress = Number(payload?.progress ?? this.progress);
                        this.monitorStatus = trackerStatus;
                        this.monitorSaved = Number(payload?.saved_count || this.monitorSaved);
                        this.monitorFailed = Number(payload?.failed_count || this.monitorFailed);
                        this.monitorItems = Number(payload?.processed_items || this.monitorItems);
                        this.monitorPages = Number(payload?.processed_pages || this.monitorPages);

                        if (['success', 'warning', 'info', 'failed'].includes(trackerStatus)) {
                            this.capturing = false;
                            this.progress = 100;
                            this.monitorMessage = trackerStatus === 'failed' ?
                                (statusResult?.message || 'Proses gagal') :
                                `Proses selesai. Saved: ${this.monitorSaved}, Failed: ${this.monitorFailed}`;
                            this.clearMonitorFromStorage();
                            
                            if(trackerStatus !== 'failed') {
                                setTimeout(() => {
                                    if(this.monitorStatus === trackerStatus) {
                                        this.monitorVisible = false;
                                    }
                                }, 5000);
                            }
                            return;
                        }

                        this.monitorMessage = statusResult?.message || 'Sedang memproses...';
                    }
                } catch (e) {
                    console.error('Polling error', e);
                }

                if (this.capturing) {
                    this.pollingInterval = setTimeout(poll, 1500);
                }
            };

            this.pollingInterval = setTimeout(poll, 1500);
        }
    }));
});
</script>
