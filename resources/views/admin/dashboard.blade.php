@extends('layouts.admin')

@section('title', 'Dashboard')

@section('page-title')
Admin Dashboard
@endsection

@section('page-description')
Monitor agents and referral performance.
@endsection

@section('content')

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <p class="text-sm text-gray-500">Agents</p>
        <h2 class="mt-2 text-4xl font-bold text-slate-900">
            {{ $totalAgents }}
        </h2>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <p class="text-sm text-gray-500">Registered Users</p>
        <h2 class="mt-2 text-4xl font-bold text-slate-900">
            {{ $totalLawyers }}
        </h2>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <p class="text-sm text-gray-500">Total Referrals</p>
        <h2 class="mt-2 text-4xl font-bold text-slate-900">
            {{ $totalReferrals }}
        </h2>
    </div>

</div>


<div class="bg-white rounded-xl shadow-sm border overflow-hidden">

    <div class="px-6 py-5 border-b">

        <h2 class="text-xl font-bold">
            Agents
        </h2>

        <p class="text-sm text-gray-500 mt-1">
            Referral performance
        </p>

    </div>


    <table class="min-w-full">

        <thead class="bg-gray-50">

        <tr>

            <th class="text-left px-6 py-4 text-sm font-semibold">
                Name
            </th>

            <th class="text-left px-6 py-4 text-sm font-semibold">
                Referral Code
            </th>

            <th class="text-left px-6 py-4 text-sm font-semibold">
                Referral Link
            </th>

            <th class="text-center px-6 py-4 text-sm font-semibold">
                Users
            </th>

            <th class="text-center px-6 py-4 text-sm font-semibold">
                Action
            </th>

        </tr>

        </thead>

        <tbody>

        @foreach($agents as $agent)

        <tr class="border-t hover:bg-gray-50">

            <td class="px-6 py-5">

                <div class="font-semibold">
                    {{ $agent->name }}
                </div>

                <div class="text-sm text-gray-500">
                    {{ ucfirst($agent->role) }}
                </div>

            </td>

            <td class="px-6 py-5">

                <span class="font-mono bg-blue-50 text-blue-700 px-3 py-1 rounded">

                    {{ $agent->referral_code }}

                </span>

            </td>

            <td class="px-6 py-5">

                <input
                    readonly
                    value="{{ url('/register?ref='.$agent->referral_code) }}"
                    class="w-full border rounded px-2 py-1 text-sm bg-gray-50">

            </td>

            <td class="text-center px-6 py-5">

                <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-100 text-blue-700 font-bold">

                    {{ $agent->referrals_count }}

                </span>

            </td>

            <td class="px-6 py-5">

    <details>

        <summary class="cursor-pointer text-blue-600 font-semibold hover:text-blue-800">
            View Referrals
        </summary>

        <div class="mt-4">

            @forelse($agent->referrals as $user)

                <div class="border rounded-lg p-4 mb-3 bg-gray-50">

                    <div class="font-semibold text-slate-900">
                        {{ $user->name }}
                    </div>

                    <div class="text-sm text-gray-500">
                        {{ $user->email }}
                    </div>

                    <div class="mt-2">

                        @if($user->email_verified_at)

                            <span class="text-green-600 font-semibold">
                                ✓ Verified
                            </span>

                        @else

                            <span class="text-red-600 font-semibold">
                                Not verified
                            </span>

                        @endif

                    </div>

                    <div class="text-xs text-gray-400 mt-2">
                        Registered:
                        {{ $user->created_at->format('d M Y') }}
                    </div>

                </div>

            @empty

                <p class="text-sm text-gray-400">
                    No registered users yet.
                </p>

            @endforelse

        </div>

    </details>

</td>

        </tr>

        @endforeach

        </tbody>

    </table>

</div>

@endsection