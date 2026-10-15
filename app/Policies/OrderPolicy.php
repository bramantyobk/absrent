<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /**
     * Pelanggan hanya dapat melihat pesanannya sendiri. Pesanan orang lain dijawab 404 agar keberadaan ID tidak terkonfirmasi.
     * Staf mengelola pesanan lewat dashboard.
     */
    public function view(User $actor, Order $order): Response
    {
        return $actor->is($order->user) ? Response::allow() : Response::denyAsNotFound();
    }
}
