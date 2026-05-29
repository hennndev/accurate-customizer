<x-app-layout>
  <x-slot name="title">Configuration</x-slot>
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
                d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
        </svg>
      </div>
      <div class="flex flex-col gap-1">
        <p class="text-[16px] font-medium text-black">Configuration</p>
        <p class="text-sm font-medium text-gray-600">Configure System</p>
      </div>
    </div>

  </x-slot>
  <div class="flex flex-col gap-6 md:gap-8 lg:gap-10">
    @if (session('success'))
      <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex gap-3">
          <svg xmlns="http://www.w3.org/2000/svg"
               fill="none"
               viewBox="0 0 24 24"
               stroke-width="1.5"
               stroke="currentColor"
               class="w-5 h-5 text-green-600 flex-shrink-0">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
          <div class="flex-1">
            <p class="text-sm font-medium text-green-900">{{ session('success') }}</p>
          </div>
        </div>
      </div>
    @endif

    @if ($errors->any())
      <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex gap-3">
          <svg xmlns="http://www.w3.org/2000/svg"
               fill="none"
               viewBox="0 0 24 24"
               stroke-width="1.5"
               stroke="currentColor"
               class="w-5 h-5 text-red-600 flex-shrink-0">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
          </svg>
          <div class="flex-1">
            <p class="text-sm font-medium text-red-900">Please fix the following errors:</p>
            <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      </div>
    @endif

    <div class="w-full bg-blue-700 rounded-xl p-5 md:p-8 lg:p-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 lg:gap-4">
      <div class="flex flex-col gap-4 md:gap-5 w-full lg:w-auto">
        <div class="flex items-center gap-3 md:gap-4">
          <div class="bg-blue-600 w-[40px] h-[40px] md:w-[50px] md:h-[50px] rounded-xl flex items-center justify-center flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke-width="1.5"
                 stroke="currentColor"
                 class="w-5 h-5 md:w-7 md:h-7 text-white">
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
              <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>

          </div>
          <div class="flex flex-col">
            <p class="text-white font-semibold text-lg md:text-xl lg:text-2xl">System Configuration</p>
            <p class="text-white text-sm md:text-base font-normal">
              Configure Your System Settings
            </p>
          </div>
        </div>
      </div>
    </div>

    <form method="POST"
          action="{{ route('configuration.update') }}">
      @csrf
      @method('PUT')

      <div class="flex border border-gray-200 flex-col gap-4 md:gap-5 rounded-xl bg-white shadow-lg p-4 md:p-5">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 sm:gap-0">
          <div class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg"
                   fill="none"
                   viewBox="0 0 24 24"
                   stroke-width="1.5"
                   stroke="currentColor"
                   class="w-5 h-5 md:w-6 md:h-6 text-blue-500">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
              </svg>

              <p class="text-sm md:text-base text-black font-medium">System Configuration</p>
            </div>
            <p class="text-xs md:text-sm lg:text-base text-gray-500 font-normal">
              Configure system settings and data retention policies
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:gap-5">
          <div class="flex flex-col gap-2">
            <label for="retention_days"
                   class="text-sm font-medium text-gray-700">
              Data Retention Days
              <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="1.5"
                     stroke="currentColor"
                     class="w-5 h-5 text-gray-400">
                  <path stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                </svg>
              </div>
              <input type="number"
                     id="retention_days"
                     name="retention_days"
                     value="{{ $setting->retention_days ?? 30 }}"
                     min="1"
                     max="365"
                     required
                     class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm md:text-base"
                     placeholder="Enter number of days">
              <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <span class="text-gray-500 text-sm">days</span>
              </div>
            </div>
            <p class="text-xs text-gray-500">
              Number of days to retain unused transaction data before automatic cleanup. Recommended:
              30-90 days
            </p>
          </div>

          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 md:p-4">
            <div class="flex gap-3">
              <svg xmlns="http://www.w3.org/2000/svg"
                   fill="none"
                   viewBox="0 0 24 24"
                   stroke-width="1.5"
                   stroke="currentColor"
                   class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
              </svg>
              <div class="flex flex-col gap-1">
                <p class="text-sm font-medium text-blue-900">About Data Retention</p>
                <p class="text-xs text-blue-700 leading-relaxed">
                  The system will automatically remove unused transaction data after the specified
                  number of days.
                  This helps maintain optimal database performance and storage efficiency. A cron job
                  runs daily to check and clean up expired data.
                </p>
              </div>
            </div>
          </div>

          {{-- Sales Invoice Number Source --}}
          <div class="flex flex-col gap-2 pt-2">
            <label class="text-sm font-medium text-gray-700">
              Sales Invoice Number Source
              <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-gray-500 -mt-1">
              Sumber nomor invoice yang dipakai untuk <code class="bg-gray-100 px-1 rounded">invoiceNo</code> pada sales-receipt dan sales-return saat migrasi.
            </p>
            <div class="flex flex-col gap-3 mt-1">
              @php $currentSource = $setting->sales_invoice_number_source ?? 'mapping_table'; @endphp

              <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition-colors
                                {{ $currentSource === 'mapping_table' ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                <input type="radio"
                       name="sales_invoice_number_source"
                       value="mapping_table"
                       class="mt-0.5 text-blue-600 focus:ring-blue-500"
                       {{ $currentSource === 'mapping_table' ? 'checked' : '' }}>
                <div class="flex flex-col gap-0.5">
                  <span class="text-sm font-medium text-gray-800">Sales Invoice Mapping Table</span>
                  <span class="text-xs text-gray-500">
                    Ambil nomor baru dari tabel <code class="bg-gray-100 px-1 rounded">sales_invoice_mapping_number</code>.
                    Gunakan opsi ini jika sudah menjalankan <em>Capture Number Mapping</em>.
                  </span>
                </div>
              </label>

              <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition-colors
                                {{ $currentSource === 'transaction_number_mappings' ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                <input type="radio"
                       name="sales_invoice_number_source"
                       value="transaction_number_mappings"
                       class="mt-0.5 text-blue-600 focus:ring-blue-500"
                       {{ $currentSource === 'transaction_number_mappings' ? 'checked' : '' }}>
                <div class="flex flex-col gap-0.5">
                  <span class="text-sm font-medium text-gray-800">Transaction Number Mappings</span>
                  <span class="text-xs text-gray-500">
                    Ambil nomor baru dari tabel <code class="bg-gray-100 px-1 rounded">transaction_number_mappings</code>.
                    Gunakan opsi ini jika mapping disimpan via mekanisme lama.
                  </span>
                </div>
              </label>
            </div>
            @error('sales_invoice_number_source')
              <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
          </div>

          {{-- Purchase Invoice Number Source --}}
          <div class="flex flex-col gap-2 pt-2">
            <label class="text-sm font-medium text-gray-700">
              Purchase Invoice Number Source
              <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-gray-500 -mt-1">
              Sumber nomor invoice yang dipakai untuk referensi pembelian saat migrasi.
            </p>
            <div class="flex flex-col gap-3 mt-1">
              @php $currentPiSource = $setting->purchase_invoice_number_source ?? 'mapping_table'; @endphp

              <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition-colors
                                {{ $currentPiSource === 'mapping_table' ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                <input type="radio"
                       name="purchase_invoice_number_source"
                       value="mapping_table"
                       class="mt-0.5 text-blue-600 focus:ring-blue-500"
                       {{ $currentPiSource === 'mapping_table' ? 'checked' : '' }}>
                <div class="flex flex-col gap-0.5">
                  <span class="text-sm font-medium text-gray-800">Purchase Invoice Mapping Table</span>
                  <span class="text-xs text-gray-500">
                    Ambil nomor baru dari tabel <code class="bg-gray-100 px-1 rounded">purchase_invoice_mapping_number</code>.
                    Gunakan opsi ini jika sudah menjalankan <em>Capture Number Mapping</em>.
                  </span>
                </div>
              </label>

              <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition-colors
                                {{ $currentPiSource === 'transaction_number_mappings' ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                <input type="radio"
                       name="purchase_invoice_number_source"
                       value="transaction_number_mappings"
                       class="mt-0.5 text-blue-600 focus:ring-blue-500"
                       {{ $currentPiSource === 'transaction_number_mappings' ? 'checked' : '' }}>
                <div class="flex flex-col gap-0.5">
                  <span class="text-sm font-medium text-gray-800">Transaction Number Mappings</span>
                  <span class="text-xs text-gray-500">
                    Ambil nomor baru dari tabel <code class="bg-gray-100 px-1 rounded">transaction_number_mappings</code>.
                    Gunakan opsi ini jika mapping disimpan via mekanisme baru.
                  </span>
                </div>
              </label>
            </div>
            @error('purchase_invoice_number_source')
              <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
          </div>

          {{-- Receive Item Number Source --}}
          <div class="flex flex-col gap-2 pt-2">
            <label class="text-sm font-medium text-gray-700">
              Receive Item Number Source
              <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-gray-500 -mt-1">
              Sumber nomor receive item yang dipakai untuk nomor item saat migrasi.
            </p>
            <div class="flex flex-col gap-3 mt-1">
              @php $currentRiSource = $setting->receive_item_number_source ?? 'mapping_table'; @endphp

              <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition-colors
                                {{ $currentRiSource === 'mapping_table' ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                <input type="radio"
                       name="receive_item_number_source"
                       value="mapping_table"
                       class="mt-0.5 text-blue-600 focus:ring-blue-500"
                       {{ $currentRiSource === 'mapping_table' ? 'checked' : '' }}>
                <div class="flex flex-col gap-0.5">
                  <span class="text-sm font-medium text-gray-800">Receive Item Mapping Table</span>
                  <span class="text-xs text-gray-500">
                    Ambil nomor baru dari tabel <code class="bg-gray-100 px-1 rounded">receive_item_mapping_number</code>.
                    Gunakan opsi ini jika sudah menjalankan <em>Capture Number Mapping</em>.
                  </span>
                </div>
              </label>

              <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition-colors
                                {{ $currentRiSource === 'transaction_number_mappings' ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                <input type="radio"
                       name="receive_item_number_source"
                       value="transaction_number_mappings"
                       class="mt-0.5 text-blue-600 focus:ring-blue-500"
                       {{ $currentRiSource === 'transaction_number_mappings' ? 'checked' : '' }}>
                <div class="flex flex-col gap-0.5">
                  <span class="text-sm font-medium text-gray-800">Transaction Number Mappings</span>
                  <span class="text-xs text-gray-500">
                    Ambil nomor baru dari tabel <code class="bg-gray-100 px-1 rounded">transaction_number_mappings</code>.
                    Gunakan opsi ini jika mapping disimpan via mekanisme baru.
                  </span>
                </div>
              </label>
            </div>
            @error('receive_item_number_source')
              <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
          </div>
        </div>
      </div>

      <button type="submit"
              class="w-full py-3 md:py-3.5 text-sm md:text-base font-semibold text-white bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
        <div class="flex items-center justify-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg"
               fill="none"
               viewBox="0 0 24 24"
               stroke-width="2"
               stroke="currentColor"
               class="w-5 h-5">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
          <span>Save Configuration</span>
        </div>
      </button>
    </form>
  </div>
</x-app-layout>
