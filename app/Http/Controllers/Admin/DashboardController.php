<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'totalUsers' => User::count(),
            'pendingOrders' => Order::where('status', Order::STATUS_PENDING)->count(),
            'paidOrders' => Order::where('status', Order::STATUS_PAID)->count(),
            'totalRevenueCents' => Order::where('status', Order::STATUS_PAID)->sum('total_cents'),
            'recentOrders' => Order::with(['items.service'])->latest()->limit(5)->get(),
        ]);
    }
}
