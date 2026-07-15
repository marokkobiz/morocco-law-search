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

<th class="px-6 py-4 text-left">Name</th>

<th class="px-6 py-4 text-left">Email</th>

<th class="px-6 py-4 text-left">Role</th>

<th class="px-6 py-4 text-left">Referral Link</th>

</tr>

</thead>

<tbody>

@foreach($users as $user)

<tr class="border-t">

<td class="px-6 py-5">

{{ $user->name }}

</td>

<td class="px-6 py-5">

{{ $user->email }}

</td>

<td class="px-6 py-5">

{{ ucfirst($user->role) }}

</td>

<td class="px-6 py-5">

@if($user->role == 'admin' || $user->role == 'agent')

<input
readonly
class="w-full border rounded px-2 py-1 bg-gray-50"
value="{{ url('/register?ref='.$user->referral_code) }}">

@else

<span class="text-gray-400">
—
</span>

@endif

</td>

</tr>

@endforeach

</tbody>

</table>

</div>

@endsection