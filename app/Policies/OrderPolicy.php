<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Pelanggan hanya dapat melihat pesanannya sendiri. Staf mengelola pesanan lewat dashboard.
     */
    public function view(User $actor, Order $order): bool
    {
        return $actor->is($order->user);
    }
}
