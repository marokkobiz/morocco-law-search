@extends('layouts.admin')

@section('title', 'Users')

@section('page-title')
Users
@endsection

@section('page-description')
Manage registered users and agents.
@endsection

@section('content')

<div class="bg-white rounded-xl shadow-sm border overflow-hidden">

<table class="min-w-full">

<thead class="bg-gray-50">

<tr>

<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Name</th>

<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Email</th>

<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Role</th>

<th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Referral Link</th>

<th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Action</th>

</tr>

</thead>

<tbody class="divide-y divide-gray-100">

@foreach($users as $user)

<tr class="border-t hover:bg-gray-50/50 transition">

<td class="px-6 py-5 text-sm font-medium text-gray-900">

{{ $user->name }}

</td>

<td class="px-6 py-5 text-sm text-gray-600">

{{ $user->email }}

</td>

<td class="px-6 py-5 text-sm text-gray-600">

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
    @if($user->role === 'admin') bg-red-50 text-red-700
    @elseif($user->role === 'agent') bg-purple-50 text-purple-700
    @else bg-gray-50 text-gray-700 @endif">
    {{ ucfirst($user->role ?? 'User') }}
</span>

</td>

<td class="px-6 py-5">

<input
readonly
class="w-full border rounded px-2 py-1 text-sm bg-gray-50 text-gray-600 font-mono"
value="{{ url('/register?ref='.$user->referral_code) }}">

</td>

<td class="px-6 py-5 text-center">
    @if($user->role !== 'admin')
        <form action="{{ route('admin.users.toggle-agent', $user->id) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition 
                @if($user->role === 'agent') 
                    bg-red-50 hover:bg-red-100 text-red-700 border-red-200 
                @else 
                    bg-green-50 hover:bg-green-100 text-green-700 border-green-200 
                @endif">
                {{ $user->role === 'agent' ? 'Remove Agent' : 'Make Agent' }}
            </button>
        </form>
    @else
        <span class="text-xs text-gray-400 italic">System Administrator</span>
    @endif
</td>

</tr>

@endforeach

</tbody>

</table>

</div>

@endsection