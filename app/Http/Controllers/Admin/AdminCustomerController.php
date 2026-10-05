<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AdminCustomerController extends Controller
{
    /**
     * Display a listing of customers with metrics, search, and filtering.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $filter = (string) $request->input('filter', 'all');
        $sort = (string) $request->input('sort', 'latest');

        $query = User::query()
            ->withCount('orders')
            ->withSum('orders as total_spent', 'total');

        // Search by name or email
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Segment filter
        match ($filter) {
            'with_orders' => $query->has('orders'),
            'no_orders' => $query->doesntHave('orders'),
            'verified' => $query->whereNotNull('email_verified_at'),
            'unverified' => $query->whereNull('email_verified_at'),
            default => null,
        };

        // Sorting
        match ($sort) {
            'oldest' => $query->oldest('created_at'),
            'most_orders' => $query->orderByDesc('orders_count'),
            'highest_spend' => $query->orderByDesc('total_spent'),
            'name' => $query->orderBy('name'),
            default => $query->latest('created_at'),
        };

        // Eager load recent 5 orders for fast preview drawer
        $query->with(['orders' => function ($q) {
            $q->where('status', '!=', 'pending_payment')
                ->latest()
                ->select('id', 'order_number', 'user_id', 'total', 'status', 'pickup_slot', 'created_at');
        }]);

        $customers = $query->paginate(15)->withQueryString()->through(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_verified' => $user->email_verified_at !== null,
                'verified_at' => $user->email_verified_at?->format('M d, Y'),
                'created_at' => $user->created_at?->format('M d, Y'),
                'created_at_human' => $user->created_at?->diffForHumans(),
                'orders_count' => (int) $user->orders_count,
                'total_spent' => round((float) ($user->total_spent ?? 0), 2),
                'recent_orders' => $user->orders->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'total' => (float) $order->total,
                        'status' => $order->status,
                        'pickup_slot' => $order->pickup_slot,
                        'created_at' => $order->created_at?->format('M d, Y · g:i A'),
                    ];
                }),
            ];
        });

        // Store customer metrics
        $totalCustomers = User::count();
        $totalOrders = Order::where('status', '!=', 'pending_payment')->count();
        $totalRevenue = (float) Order::whereNotIn('status', ['pending_payment', 'cancelled'])->sum('total');

        $metrics = [
            'total_customers' => $totalCustomers,
            'active_shoppers' => User::has('orders')->count(),
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'average_spend' => $totalCustomers > 0 ? round($totalRevenue / $totalCustomers, 2) : 0,
        ];

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'metrics' => $metrics,
            'filters' => [
                'search' => $search,
                'filter' => $filter,
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * Display specific customer details with full order history.
     */
    public function show(Request $request, User $customer): JsonResponse|Response
    {
        $customer->loadCount('orders')->loadSum('orders as total_spent', 'total');
        $orders = $customer->orders()->with('items')->latest()->get();

        $data = [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'is_verified' => $customer->email_verified_at !== null,
                'verified_at' => $customer->email_verified_at?->format('M d, Y · g:i A'),
                'created_at' => $customer->created_at?->format('M d, Y · g:i A'),
                'orders_count' => (int) $customer->orders_count,
                'total_spent' => round((float) ($customer->total_spent ?? 0), 2),
            ],
            'orders' => $orders->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'subtotal' => (float) $order->subtotal,
                    'discount' => (float) $order->discount,
                    'total' => (float) $order->total,
                    'points_earned' => $order->points_earned,
                    'status' => $order->status,
                    'payment_method' => $order->payment_method,
                    'fulfillment_type' => $order->fulfillment_type,
                    'pickup_slot' => $order->pickup_slot,
                    'pickup_location' => $order->pickup_location,
                    'notes' => $order->notes,
                    'created_at' => $order->created_at?->format('M d, Y · g:i A'),
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->name,
                            'size' => $item->size,
                            'unit_price' => (float) $item->unit_price,
                            'quantity' => $item->quantity,
                            'total_price' => (float) $item->total_price,
                            'image' => $item->image,
                        ];
                    }),
                ];
            }),
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Admin/Customers/Show', $data);
    }

    /**
     * Store a newly created customer account.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => ! empty($validated['email_verified']) ? now() : null,
        ]);

        return back()->with('success', "Customer account {$validated['name']} created successfully.");
    }

    /**
     * Update customer details.
     */
    public function update(UpdateCustomerRequest $request, User $customer): RedirectResponse
    {
        $validated = $request->validated();

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        if (array_key_exists('email_verified', $validated)) {
            $updateData['email_verified_at'] = $validated['email_verified'] ? ($customer->email_verified_at ?? now()) : null;
        }

        $customer->update($updateData);

        return back()->with('success', "Customer {$customer->name} updated successfully.");
    }

    /**
     * Remove customer account.
     */
    public function destroy(User $customer): RedirectResponse
    {
        $name = $customer->name;
        $customer->delete();

        return back()->with('success', "Customer account {$name} deleted successfully.");
    }
}
