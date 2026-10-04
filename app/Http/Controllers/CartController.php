<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * Display the shopping cart.
     */
    public function index(): Response
    {
        $impulseItems = Product::query()
            ->whereIn('slug', ['coriander', 'saunf-mints', 'green-chillies', 'masala-noodles'])
            ->get();

        return Inertia::render('Cart', [
            'impulseItems' => $impulseItems,
        ]);
    }

    /**
     * Display the checkout page.
     */
    public function checkout(): Response
    {
        return Inertia::render('Checkout', [
            'storeTimezone' => config('app.timezone'),
        ]);
    }
}
