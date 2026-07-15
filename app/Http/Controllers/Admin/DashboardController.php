<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $currentYear = 2026;

        $agents = User::whereIn('role', ['admin', 'agent'])
            ->with('referrals')
            ->get()
            ->map(function ($agent) use ($currentYear) {
                $agent->referrals_count = $agent->referrals->count();

                // 1. Initialize an array populated with 0s for months 1 to 12
                $monthsData = array_fill(1, 12, 0);

                // 2. Query database and pull the counts grouped by registration month
                $referralsByMonth = User::where('referred_by', $agent->id)
                    ->whereYear('created_at', $currentYear)
                    ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
                    ->groupBy('month')
                    ->pluck('count', 'month');

                foreach ($referralsByMonth as $month => $count) {
                    $monthsData[$month] = $count;
                }

                // 3. Attach a clean 0-indexed list of 12 numbers directly onto the agent object
                $agent->monthly_performance = array_values($monthsData);

                return $agent;
            });

        return view('admin.dashboard', [
            'agents' => $agents,
            'totalAgents' => User::whereIn('role', ['admin', 'agent'])->count(),
            'totalLawyers' => User::where('role', 'user')->count(),
            'totalReferrals' => User::whereNotNull('referred_by')->count(),
        ]);
    }
}