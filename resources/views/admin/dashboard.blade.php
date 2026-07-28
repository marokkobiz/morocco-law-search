@extends('layouts.admin')

@section('title', 'Dashboard')

@section('page-title')
Admin Dashboard
@endsection

@section('page-description')
Monitor agents and referral performance.
@endsection

@section('content')

<!-- Stat Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Active Agents</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $totalAgents }}</h2>
        </div>
        <div class="p-3 bg-purple-50 text-purple-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Registered Users</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $totalLawyers }}</h2>
        </div>
        <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Referrals</p>
            <h2 class="mt-2 text-3xl font-extrabold text-slate-900">{{ $totalReferrals }}</h2>
        </div>
        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        </div>
    </div>

</div>

<!-- Agents Table -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

    <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Agent Performance</h2>
            <p class="text-xs text-slate-500 mt-0.5">Track individual agent acquisition and user conversions.</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">Agent Name</th>
                    <th class="px-6 py-4">Code</th>
                    <th class="px-6 py-4">Referral Link</th>
                    <th class="px-6 py-4 text-center">Referrals</th>
                    <th class="px-6 py-4 text-right">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                @foreach($agents as $agent)
                <tr class="hover:bg-slate-50/70 transition">
                    <td class="px-6 py-4 font-medium text-slate-900">
                        <div>{{ $agent->name }}</div>
                        <div class="text-xs text-slate-400 font-normal">{{ $agent->email }}</div>
                    </td>

                    <td class="px-6 py-4">
                        <span class="font-mono bg-slate-100 text-slate-700 px-2.5 py-1 rounded text-xs border border-slate-200 font-semibold">
                            {{ $agent->referral_code }}
                        </span>
                    </td>

                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2 max-w-xs">
                            <input readonly value="{{ url('/register?ref='.$agent->referral_code) }}"
                                   class="w-full bg-slate-50 border border-slate-200 rounded px-2.5 py-1 text-xs text-slate-600 font-mono truncate focus:outline-none">
                            <button onclick="copyToClipboard('{{ url('/register?ref='.$agent->referral_code) }}', this)"
                                    class="text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 px-2.5 py-1 rounded border border-slate-200 transition shrink-0">
                                Copy
                            </button>
                        </div>
                    </td>

                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-100">
                            {{ $agent->referrals_count }}
                        </span>
                    </td>

                    <td class="px-6 py-4 text-right">
                        <details class="group" ontoggle="if(this.open) { initAgentChart('{{ $agent->id }}') }">
                            <summary class="cursor-pointer inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-800 focus:outline-none select-none">
                                <span>View Referrals</span>
                                <svg class="w-4 h-4 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>

                            <!-- Accordion Content -->
                            <div class="mt-4 p-6 bg-slate-50 rounded-xl border border-slate-200 text-left">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    
                                    <!-- User List -->
                                    <div>
                                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-3">
                                            📋 Registered Users
                                        </h3>
                                        <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                                            @forelse($agent->referrals as $user)
                                                <div class="bg-white p-3 rounded-lg border border-slate-200 flex items-center justify-between shadow-sm">
                                                    <div>
                                                        <div class="font-semibold text-xs text-slate-900">{{ $user->name }}</div>
                                                        <div class="text-[11px] text-slate-500">{{ $user->email }}</div>
                                                    </div>
                                                    <div>
                                                        @if($user->email_verified_at)
                                                            <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                                                ✓ Verified
                                                            </span>
                                                        @else
                                                            <span class="text-[10px] font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                                                Pending
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @empty
                                                <p class="text-xs text-slate-400 italic">No users registered under this referral link yet.</p>
                                            @endforelse
                                        </div>
                                    </div>

                                    <!-- Chart Canvas Container -->
                                    <div>
                                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-3">
                                            📊 Monthly Trend (2026)
                                        </h3>
                                        <div class="bg-white border border-slate-200 rounded-xl p-3 h-52">
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

</div>

<!-- Chart.js CDN Asset -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const renderedCharts = {};

    function initAgentChart(agentId) {
        const dataPayload = @json($agents->keyBy('id'));
        const monthlyMetrics = dataPayload[agentId].monthly_performance;
        const targetCanvasCtx = document.getElementById(`chart-${agentId}`).getContext('2d');

        if (renderedCharts[agentId]) {
            renderedCharts[agentId].destroy();
        }

        renderedCharts[agentId] = new Chart(targetCanvasCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Registrations',
                    data: monthlyMetrics,
                    backgroundColor: 'rgba(37, 99, 235, 0.85)',
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } } },
                    x: { ticks: { font: { size: 10 } }, grid: { display: false } }
                }
            }
        });
    }
</script>

@endsection