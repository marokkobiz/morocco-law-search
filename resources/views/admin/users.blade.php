@extends('layouts.admin')

@section('title', 'Users')

@section('page-title')
User & Agent Management
@endsection

@section('page-description')
Manage platform user roles, agent privileges, and referral links.
@endsection

@section('content')

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">User Details</th>
                    <th class="px-6 py-4">Role</th>
                    <th class="px-6 py-4">Referral Link</th>
                    <th class="px-6 py-4 text-center">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 text-sm">
                @foreach($users as $user)
                <tr class="hover:bg-slate-50/70 transition">
                    <!-- User Details -->
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-900">{{ $user->name }}</div>
                        <div class="text-xs text-slate-500">{{ $user->email }}</div>
                    </td>

                    <!-- Role Badge -->
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border
                            @if($user->role === 'admin') bg-rose-50 text-rose-700 border-rose-200
                            @elseif($user->role === 'agent') bg-purple-50 text-purple-700 border-purple-200
                            @else bg-slate-100 text-slate-700 border-slate-200 @endif">
                            {{ ucfirst($user->role ?? 'User') }}
                        </span>
                    </td>

                    <!-- Referral Link -->
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2 max-w-sm">
                            <input readonly value="{{ url('/register?ref='.$user->referral_code) }}"
                                   class="w-full bg-slate-50 border border-slate-200 rounded px-2.5 py-1 text-xs text-slate-600 font-mono truncate focus:outline-none">
                            <button onclick="copyToClipboard('{{ url('/register?ref='.$user->referral_code) }}', this)"
                                    class="text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 px-2.5 py-1 rounded border border-slate-200 transition shrink-0">
                                Copy
                            </button>
                        </div>
                    </td>

                    <!-- Action Button -->
                    <td class="px-6 py-4 text-center">
                        @if($user->role !== 'admin')
                            <form action="{{ route('admin.users.toggle-agent', $user->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" 
                                        class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm
                                        @if($user->role === 'agent')
                                            bg-white hover:bg-rose-50 text-rose-600 border-rose-200 hover:border-rose-300
                                        @else
                                            bg-slate-900 hover:bg-slate-800 text-white border-transparent
                                        @endif">
                                    {{ $user->role === 'agent' ? 'Demote to User' : 'Promote to Agent' }}
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-slate-400 italic">System Admin</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>

@endsection