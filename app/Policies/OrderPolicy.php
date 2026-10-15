<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\TransactionStatus;
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

    /**
     * Membayar (halaman pembayaran, konfirmasi) dan membatalkan langsung hanya selama pesanan `Pending`
     * dan pembayarannya belum dikonfirmasi.
     */
    public function pay(User $actor, Order $order): Response
    {
        $view = $this->view($actor, $order);

        if ($view->denied()) {
            return $view;
        }

        $isUnconfirmed = $order->status === OrderStatus::Pending && $order->transaction?->status === TransactionStatus::Pending;

        return $isUnconfirmed ? Response::allow() : Response::deny('Pesanan ini tidak lagi menunggu pembayaran.');
    }

    /**
     * Membuka lagi pesan konfirmasi ke operator, hanya selama menunggu verifikasi.
     */
    public function resendConfirmation(User $actor, Order $order): Response
    {
        $view = $this->view($actor, $order);

        if ($view->denied()) {
            return $view;
        }

        $isVerifying = $order->status === OrderStatus::Pending && $order->transaction?->status === TransactionStatus::Verifying;

        return $isVerifying ? Response::allow() : Response::deny('Pesanan ini tidak sedang menunggu verifikasi.');
    }
}
