<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSupplierController extends Controller
{
    /**
     * Display a listing of suppliers with product associations, search, and metrics.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');
        $sort = (string) $request->input('sort', 'name');

        $query = Supplier::query()->withCount('products')->with([
            'products:id,supplier_id,name,price,image,stock,category',
        ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('status', 'active');
        } elseif ($status === 'inactive') {
            $query->where('status', 'inactive');
        }

        match ($sort) {
            'products_count' => $query->orderByDesc('products_count')->orderBy('name'),
            'lead_time' => $query->orderBy('lead_time_days')->orderBy('name'),
            'latest' => $query->latest('created_at'),
            default => $query->orderBy('name'),
        };

        $suppliers = $query->paginate(15)->withQueryString()->through(function (Supplier $supplier) {
            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'code' => $supplier->code,
                'contact_person' => $supplier->contact_person,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'city' => $supplier->city,
                'state' => $supplier->state,
                'country' => $supplier->country,
                'postal_code' => $supplier->postal_code,
                'lead_time_days' => (int) $supplier->lead_time_days,
                'payment_terms' => $supplier->payment_terms,
                'status' => $supplier->status,
                'notes' => $supplier->notes,
                'products_count' => (int) $supplier->products_count,
                'products' => $supplier->products->take(5)->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => (float) $p->price,
                    'stock' => (int) $p->stock,
                    'image' => $p->image,
                ]),
                'created_at' => $supplier->created_at?->format('M d, Y'),
            ];
        });

        $metrics = [
            'total_suppliers' => Supplier::count(),
            'active_suppliers' => Supplier::where('status', 'active')->count(),
            'inactive_suppliers' => Supplier::where('status', 'inactive')->count(),
            'total_supplied_products' => Product::whereNotNull('supplier_id')->count(),
            'total_products_linked' => Product::whereNotNull('supplier_id')->count(),
            'avg_lead_time' => round((float) (Supplier::avg('lead_time_days') ?? 2), 1),
            'avg_lead_time_days' => round((float) (Supplier::avg('lead_time_days') ?? 2), 1),
        ];

        return Inertia::render('Admin/Suppliers/Index', [
            'suppliers' => $suppliers,
            'metrics' => $metrics,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * Store a newly created supplier in storage.
     */
    public function store(StoreSupplierRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $supplier = Supplier::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Supplier '{$supplier->name}' registered successfully.",
                'supplier' => $supplier,
            ], 201);
        }

        return redirect()->route('admin.suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' registered successfully.");
    }

    /**
     * Update the specified supplier in storage.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $supplier->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Supplier '{$supplier->name}' updated successfully.",
                'supplier' => $supplier,
            ]);
        }

        return redirect()->route('admin.suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' updated successfully.");
    }

    /**
     * Quick toggle for active/inactive supplier status.
     */
    public function toggleStatus(Supplier $supplier): RedirectResponse|JsonResponse
    {
        $newStatus = $supplier->status === 'active' ? 'inactive' : 'active';
        $supplier->update(['status' => $newStatus]);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'status' => $newStatus,
                'message' => "Supplier '{$supplier->name}' is now {$newStatus}.",
            ]);
        }

        return redirect()->back()
            ->with('success', "Supplier '{$supplier->name}' is now {$newStatus}.");
    }

    /**
     * Remove the specified supplier from storage.
     */
    public function destroy(Supplier $supplier): RedirectResponse|JsonResponse
    {
        $name = $supplier->name;
        $supplier->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Supplier '{$name}' deleted successfully.",
            ]);
        }

        return redirect()->route('admin.suppliers.index')
            ->with('success', "Supplier '{$name}' deleted successfully.");
    }
}
