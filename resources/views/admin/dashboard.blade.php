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

            <th class="text-left px-6 py-4 text-sm font-semibold text-gray-700">
                Name
            </th>

            <th class="text-left px-6 py-4 text-sm font-semibold text-gray-700">
                Referral Code
            </th>

            <th class="text-left px-6 py-4 text-sm font-semibold text-gray-700">
                Referral Link
            </th>

            <th class="text-center px-6 py-4 text-sm font-semibold text-gray-700">
                Users
            </th>

            <th class="text-center px-6 py-4 text-sm font-semibold text-gray-700">
                Action
            </th>

        </tr>

        </thead>

        <tbody>

        @foreach($agents as $agent)

        <tr class="border-t hover:bg-gray-50">

            <td class="px-6 py-5">

                <div class="font-semibold text-gray-900">
                    {{ $agent->name }}
                </div>

                <div class="text-sm text-gray-500">
                    {{ ucfirst($agent->role) }}
                </div>

            </td>

            <td class="px-6 py-5">

                <span class="font-mono bg-blue-50 text-blue-700 px-3 py-1 rounded text-sm">
                    {{ $agent->referral_code }}
                </span>

            </td>

            <td class="px-6 py-5">

                <input
                    readonly
                    value="{{ url('/register?ref='.$agent->referral_code) }}"
                    class="w-full border rounded px-2 py-1 text-sm bg-gray-50 text-gray-600">

            </td>

            <td class="text-center px-6 py-5">

                <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-100 text-blue-700 font-bold">
                    {{ $agent->referrals_count }}
                </span>

            </td>

            <td class="px-6 py-5">

            <details ontoggle="if(this.open) { initAgentChart('{{ $agent->id }}') }">

                <summary class="cursor-pointer text-center text-blue-600 font-semibold hover:text-blue-800 focus:outline-none list-none select-none">
                    View Referrals
                </summary>

                <div class="mt-6 pt-6 border-t border-gray-100">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
                        
                        <!-- Left Panel: Referred Users List -->
                        <div>
                            <h3 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                                📋 Invited Registrations
                            </h3>
                            
                            <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                                @forelse($agent->referrals as $user)

                                    <div class="border rounded-lg p-4 bg-gray-50/50 hover:bg-gray-50 transition flex items-center justify-between">
                                        <div>
                                            <div class="font-semibold text-sm text-slate-900">
                                                {{ $user->name }}
                                            </div>
                                            <div class="text-xs text-gray-500 mt-0.5">
                                                {{ $user->email }}
                                            </div>
                                            <div class="text-[10px] text-gray-400 mt-2">
                                                Registered: {{ $user->created_at->format('d M Y') }}
                                            </div>
                                        </div>

                                        <div>
                                            @if($user->email_verified_at)
                                                <span class="inline-flex items-center text-xs font-semibold text-green-600 bg-green-50 px-2 py-0.5 rounded border border-green-100">
                                                    ✓ Verified
                                                </span>
                                            @else
                                                <span class="inline-flex items-center text-xs font-semibold text-red-600 bg-red-50 px-2 py-0.5 rounded border border-red-100">
                                                    Not verified
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                @empty

                                    <p class="text-sm text-gray-400 italic">
                                        No registered users found for this agent.
                                    </p>

                                @endforelse
                            </div>
                        </div>

                        <!-- Right Panel: Chart.js Canvas Container -->
                        <div>
                            <h3 class="text-sm font-bold text-gray-800 mb-4">
                                📊 Monthly Performance Trend (2026)
                            </h3>
                            <div class="bg-gray-50 border rounded-xl p-4 h-64 flex items-center justify-center relative">
                                <canvas id="chart-{{ $agent->id }}"></canvas>
                            </div>
                        </div>

                    </div>

                </div>

            </details>

        </td>

        </tr>

        @endforeach

        </tbody>

    </table>

</div>

<!-- Load Chart.js CDN Asset -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Global tracking storage for active chart entities
    const renderedCharts = {};

    function initAgentChart(agentId) {
        // Safely extract compiled mapped agent arrays from our Blade loop
        const dataPayload = @json($agents->keyBy('id'));
        const monthlyMetrics = dataPayload[agentId].monthly_performance;

        const targetCanvasCtx = document.getElementById(`chart-${agentId}`).getContext('2d');

        // Prevent layout duplication errors if double toggled
        if (renderedCharts[agentId]) {
            renderedCharts[agentId].destroy();
        }

        // Build elegant Chart.js Bar Chart matching light theme parameters
        renderedCharts[agentId] = new Chart(targetCanvasCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Registrations',
                    data: monthlyMetrics,
                    backgroundColor: 'rgba(59, 130, 246, 0.85)', // Tailwind Blue 500
                    borderColor: 'rgb(37, 99, 235)', // Tailwind Blue 600
                    borderWidth: 1,
                    borderRadius: 5,
                    barPercentage: 0.65
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#ffffff',
                        bodyColor: '#cbd5e1',
                        padding: 10,
                        cornerRadius: 6
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            color: '#64748b',
                            font: { size: 11 }
                        },
                        grid: {
                            color: '#f1f5f9'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#64748b',
                            font: { size: 11 }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
</script>

@endsection