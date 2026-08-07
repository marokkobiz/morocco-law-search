@extends('layouts.admin')

@section('title', 'Users')

@section('page-title')
User Management
@endsection

@section('page-description')
View all registered platform users.
@endsection

@section('content')

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">User Details</th>
                    <th class="px-6 py-4">Role</th>
                    <th class="px-6 py-4">Joined</th>
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
                        <div class="text-xs text-slate-500">{{ $user->company }}</div>
                    </td>

                    <!-- Role Badge -->
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border
                            @if($user->role === 'admin') bg-rose-50 text-rose-700 border-rose-200
                            @else bg-slate-100 text-slate-700 border-slate-200 @endif">
                            {{ ucfirst($user->role ?? 'User') }}
                        </span>
                    </td>

                    <!-- Joined -->
                    <td class="px-6 py-4 text-slate-500">
                        {{ $user->created_at->format('d M Y') }}
                    </td>

                    <!-- Actions -->
                    <td class="px-6 py-4 text-center">
                        @if($user->id !== auth()->id())
                            <div class="flex items-center justify-center gap-2">
                                <form action="{{ route('admin.users.toggle-admin', $user->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm
                                            @if($user->role === 'admin')
                                                bg-white hover:bg-rose-50 text-rose-600 border-rose-200 hover:border-rose-300
                                            @else
                                                bg-slate-900 hover:bg-slate-800 text-white border-transparent
                                            @endif">
                                        {{ $user->role === 'admin' ? 'Remove Admin' : 'Make Admin' }}
                                    </button>
                                </form>

                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Delete this user permanently?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition shadow-sm bg-white hover:bg-rose-50 text-rose-600 border-rose-200 hover:border-rose-300">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        @else
                            <span class="text-xs text-slate-400 italic">You</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>

@endsection
