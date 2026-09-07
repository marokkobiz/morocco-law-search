<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        return view('admin.orders.index', [
            'orders' => Order::with(['items.service'])->latest()->paginate(10),
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['items.service']);
        return view('admin.orders.show', [
            'order' => $order,
        ]);
    }
}
