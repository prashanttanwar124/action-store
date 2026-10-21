<?php

use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('admin.orders', function ($user) {
    $admin = $user instanceof Admin ? $user : Auth::guard('admin')->user();

    return $admin instanceof Admin && $admin->can('manage orders');
}, ['guards' => ['admin', 'web']]);

Broadcast::channel('orders.{orderNumber}', function ($user, string $orderNumber) {
    $cleanNumber = ltrim($orderNumber, '#');
    $hashNumber = '#'.$cleanNumber;

    $order = Order::where('order_number', $cleanNumber)
        ->orWhere('order_number', $hashNumber)
        ->first();

    if (! $order) {
        return false;
    }

    $admin = $user instanceof Admin ? $user : Auth::guard('admin')->user();
    if ($admin instanceof Admin && $admin->can('manage orders')) {
        return true;
    }

    $webUser = $user instanceof User ? $user : Auth::guard('web')->user();
    if ($webUser instanceof User && $order->user_id && (int) $webUser->id === (int) $order->user_id) {
        return true;
    }

    return in_array($order->order_number, (array) session('placed_order_numbers', []), true);
}, ['guards' => ['web', 'admin']]);
