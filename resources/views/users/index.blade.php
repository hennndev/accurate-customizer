<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-9 h-9 rounded-[14px] bg-[linear-gradient(135deg,#155DFC_0%,#4F39F6_100%)] shadow-[0_10px_15px_-3px_rgba(0,0,0,0.10),0_4px_6px_-4px_rgba(0,0,0,0.10)] flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="size-5 text-white">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
            </div>
            <div class="flex flex-col gap-1">
                <p class="text-[16px] font-medium text-black">Users</p>
                <p class="text-sm font-medium text-gray-600">Manage system users and permissions</p>
            </div>
        </div>
    </x-slot>

    <div class="flex flex-col gap-6 md:gap-8 lg:gap-10" x-data="{
        showModal: false,
        showEditModal: false,
        showDeleteModal: false,
        editingUser: {},
        deletingUser: {},
        formErrors: {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            role: '',
            status: ''
        },
        validateAddUserForm() {
            // Reset errors
            this.formErrors = {
                name: '',
                email: '',
                password: '',
                password_confirmation: '',
                role: '',
                status: ''
            };
    
            let isValid = true;
            const form = document.getElementById('addUserForm');
            const formData = new FormData(form);
    
            // Name validation
            if (!formData.get('name') || formData.get('name').trim() === '') {
                this.formErrors.name = 'Name is required';
                isValid = false;
            }
    
            // Email validation
            const email = formData.get('email');
            if (!email || email.trim() === '') {
                this.formErrors.email = 'Email is required';
                isValid = false;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                this.formErrors.email = 'Please enter a valid email address';
                isValid = false;
            }
    
            // Role validation
            if (!formData.get('role')) {
                this.formErrors.role = 'Role is required';
                isValid = false;
            }
    
            // Password validation
            const password = formData.get('password');
            if (!password || password.trim() === '') {
                this.formErrors.password = 'Password is required';
                isValid = false;
            } else if (password.length < 8) {
                this.formErrors.password = 'Password must be at least 8 characters';
                isValid = false;
            }
    
            // Confirm password validation
            const passwordConfirmation = formData.get('password_confirmation');
            if (!passwordConfirmation || passwordConfirmation.trim() === '') {
                this.formErrors.password_confirmation = 'Please confirm your password';
                isValid = false;
            } else if (password !== passwordConfirmation) {
                this.formErrors.password_confirmation = 'Passwords do not match';
                isValid = false;
            }
    
            // Status validation
            if (!formData.get('status')) {
                this.formErrors.status = 'Status is required';
                isValid = false;
            }
    
            return isValid;
        },
        submitAddUserForm() {
            if (this.validateAddUserForm()) {
                document.getElementById('addUserForm').submit();
            }
        }
    }">
        <div
            class="w-full bg-gradient-to-r from-purple-700 to-purple-600 rounded-xl p-5 md:p-8 lg:p-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 lg:gap-4">
            <div class="flex flex-col gap-4 md:gap-5 w-full lg:w-auto">
                <div class="flex items-center gap-3 md:gap-4">
                    <div
                        class="bg-white/20 w-[40px] h-[40px] md:w-[50px] md:h-[50px] rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5 md:w-7 md:h-7 text-white">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <p class="text-white font-semibold text-lg md:text-xl lg:text-2xl">User Management</p>
                        <p class="text-white text-sm md:text-base font-normal">Manage system users and permissions</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-6 md:gap-10 w-full lg:w-auto justify-end">
                <div class="flex flex-col gap-1">
                    <p class="text-white font-medium text-xs md:text-sm">Total Users</p>
                    <p class="text-2xl md:text-3xl font-bold text-white self-end">{{ $users->count() }}</p>
                </div>
            </div>
        </div>

        <div class="flex border border-gray-200 flex-col gap-4 md:gap-5 rounded-xl bg-white shadow-lg p-4 md:p-5">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 sm:gap-0">
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5 md:w-6 md:h-6 text-purple-500">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>

                        <p class="text-sm md:text-base text-black font-medium">Users</p>
                    </div>
                    <p class="text-xs md:text-sm lg:text-base text-gray-500 font-normal">
                        Create and manage user accounts
                    </p>
                </div>
                <button @click="showModal = true"
                    class="flex items-center py-2 px-4 md:px-5 rounded-lg bg-gradient-to-tr from-purple-700 to-violet-600 text-white font-semibold hover:shadow-lg hover:from-purple-800 hover:to-violet-700 transition text-sm md:text-[15px] w-full sm:w-auto justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-4 h-4 md:w-5 md:h-5 text-white inline-block mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                    </svg>
                    Add New User
                </button>
            </div>

            {{-- table --}}
            <div class="w-full overflow-hidden border border-gray-200 rounded-lg">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse min-w-[800px]">
                        <thead class="bg-gray-100">
                            <tr class="border-b border-gray-200">
                                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">Name
                                </th>
                                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">Email
                                </th>
                                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">Role
                                </th>
                                <th class="p-2 md:p-4 text-center text-xs md:text-sm font-semibold text-gray-700">Status
                                </th>
                                <th class="p-2 md:p-4 text-left text-xs md:text-sm font-semibold text-gray-700">Created
                                </th>
                                <th class="p-2 md:p-4 text-center text-xs md:text-sm font-semibold text-gray-700">Action
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            @forelse ($users as $user)
                                <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                                    <td class="p-2 md:p-4">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="text-xs md:text-sm font-medium text-gray-900">{{ $user->name }}</span>
                                        </div>
                                    </td>
                                    <td class="p-2 md:p-4 text-xs md:text-sm text-gray-600">{{ $user->email }}</td>
                                    <td class="p-2 md:p-4">
                                        @if ($user->role === 'Super Admin')
                                            <span
                                                class="inline-flex items-center gap-1 px-2 md:px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                                    class="w-3 h-3">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                                </svg>
                                                Super Admin
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center gap-1 px-2 md:px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                                    class="w-3 h-3">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                </svg>
                                                Admin
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-2 md:p-4 text-center">
                                        @if ($user->status === 'Active')
                                            <span
                                                class="inline-flex items-center gap-1 px-2 md:px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                                    class="w-3 h-3">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                                Active
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center gap-1 px-2 md:px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                                    class="w-3 h-3">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                                Inactive
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-2 md:p-4 text-xs md:text-sm text-gray-600">
                                        {{ $user->created_at->format('d M Y') }}</td>
                                    <td class="p-2 md:p-4">
                                        <div class="flex items-center justify-center gap-1 md:gap-2">
                                            <button
                                                @click="editingUser = { id: {{ $user->id }}, name: '{{ $user->name }}', email: '{{ $user->email }}', role: '{{ $user->role }}', status: '{{ $user->status }}' }; showEditModal = true"
                                                class="inline-flex items-center justify-center p-1.5 md:p-2 rounded-lg hover:bg-blue-50 text-blue-600 hover:text-blue-700 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                    class="w-4 h-4 md:w-5 md:h-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                </svg>
                                            </button>
                                            @if ($user->email !== Auth::user()->email)
                                                <button
                                                    @click="deletingUser = { id: {{ $user->id }}, name: '{{ $user->name }}', email: '{{ $user->email }}' }; showDeleteModal = true"
                                                    class="inline-flex items-center justify-center p-1.5 md:p-2 rounded-lg hover:bg-red-50 text-red-600 hover:text-red-700 transition">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                        class="w-4 h-4 md:w-5 md:h-5">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-gray-500">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                class="w-12 h-12 text-gray-400">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                            </svg>
                                            <p class="text-sm font-medium">No users found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        {{-- Add User Modal --}}
        <div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title"
            role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Background overlay --}}
                <div x-show="showModal" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0" @click="showModal = false"
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                {{-- Center modal --}}
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {{-- Modal panel --}}
                <div x-show="showModal" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all max-sm:w-full sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">

                    {{-- Modal Header --}}
                    <div class="bg-gradient-to-r from-purple-700 to-violet-600 px-4 py-5 sm:px-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg leading-6 font-semibold text-white" id="modal-title">
                                Add New User
                            </h3>
                            <button @click="showModal = false" class="text-white hover:text-gray-200 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Modal Body --}}
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <form method="POST" action="{{ route('users.store') }}" class="space-y-4"
                            id="addUserForm">
                            @csrf
                            {{-- Name Field --}}
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="name" name="name"
                                    :class="formErrors.name ? 'border-red-500' : 'border-gray-300'"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                                    placeholder="John Doe" @input="formErrors.name = ''" required>
                                <p x-show="formErrors.name" x-text="formErrors.name"
                                    class="mt-1 text-xs text-red-500"></p>
                            </div>

                            {{-- Email Field --}}
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="email" name="email"
                                    :class="formErrors.email ? 'border-red-500' : 'border-gray-300'"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                                    placeholder="john.doe@example.com" @input="formErrors.email = ''" required>
                                <p x-show="formErrors.email" x-text="formErrors.email"
                                    class="mt-1 text-xs text-red-500"></p>
                            </div>

                            {{-- Role Field --}}
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">
                                    Role <span class="text-red-500">*</span>
                                </label>
                                <select id="role" name="role"
                                    :class="formErrors.role ? 'border-red-500' : 'border-gray-300'"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                                    @change="formErrors.role = ''" required>
                                    <option value="">Select role</option>
                                    <option value="Super Admin">Super Admin</option>
                                    <option value="Admin">Admin</option>
                                </select>
                                <p x-show="formErrors.role" x-text="formErrors.role"
                                    class="mt-1 text-xs text-red-500"></p>
                            </div>

                            {{-- Password Field --}}
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                    Password <span class="text-red-500">*</span>
                                </label>
                                <input type="password" id="password" name="password"
                                    :class="formErrors.password ? 'border-red-500' : 'border-gray-300'"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                                    placeholder="••••••••" @input="formErrors.password = ''" required>
                                <p x-show="formErrors.password" x-text="formErrors.password"
                                    class="mt-1 text-xs text-red-500"></p>
                            </div>

                            {{-- Confirm Password Field --}}
                            <div>
                                <label for="password_confirmation"
                                    class="block text-sm font-medium text-gray-700 mb-1">
                                    Confirm Password <span class="text-red-500">*</span>
                                </label>
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                    :class="formErrors.password_confirmation ? 'border-red-500' : 'border-gray-300'"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                                    placeholder="••••••••" @input="formErrors.password_confirmation = ''" required>
                                <p x-show="formErrors.password_confirmation" x-text="formErrors.password_confirmation"
                                    class="mt-1 text-xs text-red-500"></p>
                            </div>

                            {{-- Status Field --}}
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                                    Status <span class="text-red-500">*</span>
                                </label>
                                <select id="status" name="status"
                                    :class="formErrors.status ? 'border-red-500' : 'border-gray-300'"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                                    @change="formErrors.status = ''" required>
                                    <option value="">Select status</option>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                                <p x-show="formErrors.status" x-text="formErrors.status"
                                    class="mt-1 text-xs text-red-500"></p>
                            </div>
                        </form>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button type="button" @click="submitAddUserForm()"
                            class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-gradient-to-r from-purple-700 to-violet-600 text-sm font-semibold text-white hover:from-purple-800 hover:to-violet-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:w-auto">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Create User
                        </button>
                        <button type="button" @click="showModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:mt-0 sm:w-auto">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Edit User Modal --}}
        <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title"
            role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Background overlay --}}
                <div x-show="showEditModal" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0" @click="showEditModal = false"
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                {{-- Center modal --}}
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {{-- Modal panel --}}
                <div x-show="showEditModal" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all max-sm:w-full sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">

                    {{-- Modal Header --}}
                    <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-4 py-5 sm:px-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg leading-6 font-semibold text-white" id="modal-title">
                                Edit User
                            </h3>
                            <button @click="showEditModal = false" class="text-white hover:text-gray-200 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Modal Body --}}
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <form method="POST" :action="`{{ route('users.index') }}/${editingUser.id}`"
                            class="space-y-4" id="editUserForm">
                            @csrf
                            @method('PUT')
                            {{-- Name Field --}}
                            <div>
                                <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="edit_name" name="name" x-model="editingUser.name"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                    placeholder="John Doe" required>
                            </div>

                            {{-- Email Field --}}
                            <div>
                                <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-1">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="edit_email" name="email" x-model="editingUser.email"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                    placeholder="john.doe@example.com" required>
                            </div>

                            {{-- Role Field --}}
                            <div>
                                <label for="edit_role" class="block text-sm font-medium text-gray-700 mb-1">
                                    Role <span class="text-red-500">*</span>
                                </label>
                                <select id="edit_role" name="role" x-model="editingUser.role"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                    required>
                                    <option value="">Select role</option>
                                    <option value="Super Admin">Super Admin</option>
                                    <option value="Admin">Admin</option>
                                </select>
                            </div>

                            {{-- Password Field --}}
                            <div>
                                <label for="edit_password" class="block text-sm font-medium text-gray-700 mb-1">
                                    Password <span class="text-gray-400 text-xs">(Leave blank to keep current)</span>
                                </label>
                                <input type="password" id="edit_password" name="password"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                    placeholder="••••••••">
                            </div>

                            {{-- Confirm Password Field --}}
                            <div>
                                <label for="edit_password_confirmation"
                                    class="block text-sm font-medium text-gray-700 mb-1">
                                    Confirm Password
                                </label>
                                <input type="password" id="edit_password_confirmation" name="password_confirmation"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                    placeholder="••••••••">
                            </div>

                            {{-- Status Field --}}
                            <div>
                                <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-1">
                                    Status <span class="text-red-500">*</span>
                                </label>
                                <select id="edit_status" name="status" x-model="editingUser.status"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                    required>
                                    <option value="">Select status</option>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button type="submit" form="editUserForm"
                            class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-gradient-to-r from-blue-700 to-blue-600 text-sm font-semibold text-white hover:from-blue-800 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            Update User
                        </button>
                        <button type="button" @click="showEditModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Delete User Modal --}}
        <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Background overlay --}}
                <div x-show="showDeleteModal" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0" @click="showDeleteModal = false"
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                {{-- Center modal --}}
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {{-- Modal panel --}}
                <div x-show="showDeleteModal" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all max-sm:w-full sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">

                    {{-- Modal Header --}}
                    <div class="bg-gradient-to-r from-red-700 to-red-600 px-4 py-5 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-white/20">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="2" stroke="currentColor" class="w-6 h-6 text-white">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg leading-6 font-semibold text-white" id="modal-title">
                                    Delete User
                                </h3>
                            </div>
                            <button @click="showDeleteModal = false"
                                class="text-white hover:text-gray-200 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Modal Body --}}
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                        <div class="flex flex-col gap-4">
                            <div class="text-center sm:text-left">
                                <p class="text-sm text-gray-600">
                                    Are you sure you want to delete this user? This action cannot be undone.
                                </p>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <div class="flex flex-col gap-2">
                                    <div class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-500">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                        </svg>
                                        <p class="text-sm font-semibold text-gray-900" x-text="deletingUser.name"></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-500">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                        </svg>
                                        <p class="text-sm text-gray-600" x-text="deletingUser.email"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <form method="POST" :action="`{{ route('users.index') }}/${deletingUser.id}`"
                            id="deleteUserForm">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-gradient-to-r from-red-700 to-red-600 text-sm font-semibold text-white hover:from-red-800 hover:to-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:w-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                                Delete User
                            </button>
                        </form>
                        <button type="button" @click="showDeleteModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:w-auto">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
