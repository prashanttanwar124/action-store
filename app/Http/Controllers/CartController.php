<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * Display the shopping cart.
     */
    public function index(): Response
    {
        return Inertia::render('Cart');
    }

    /**
     * Display the checkout page.
     */
    public function checkout(): Response
    {
        return Inertia::render('Checkout');
    }
}
