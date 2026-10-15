<?php

namespace App\View\Components\Customer;

use App\Services\CheckoutCart;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

class Navbar extends Component
{
    public bool $isCustomer;

    public int $cartCount;

    public function __construct(Request $request, CheckoutCart $cart)
    {
        $this->isCustomer = $request->user() !== null && ! $request->user()->isStaff();
        $this->cartCount = $this->isCustomer ? $cart->count() : 0;
    }

    public function render(): View|Closure|string
    {
        return view('components.customer.navbar');
    }
}
