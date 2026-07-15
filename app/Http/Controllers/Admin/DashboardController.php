<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $agents = User::whereIn('role', ['admin', 'agent'])
    ->with('referrals')
    ->get()
    ->map(function ($agent) {
        $agent->referrals_count = $agent->referrals->count();

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