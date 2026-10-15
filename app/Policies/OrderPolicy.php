<?php

namespace App\Policies;

use App\CancellationStatus;
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
     * Staf (admin dan operator) mengelola semua pesanan lewat dashboard.
     */
    public function manage(User $actor): bool
    {
        return $actor->isStaff();
    }

    /**
     * Menandai pesanan Disewa sebagai Dicuri hanya oleh admin.
     */
    public function markStolen(User $actor, Order $order): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Mengajukan pembatalan: hanya untuk pesanan Lunas yang belum Disewa (status Booking) dan belum punya pengajuan menunggu.
     */
    public function requestCancellation(User $actor, Order $order): Response
    {
        $view = $this->view($actor, $order);

        if ($view->denied()) {
            return $view;
        }

        if ($order->status !== OrderStatus::Booking || $order->transaction?->status !== TransactionStatus::Paid) {
            return Response::deny('Pembatalan hanya dapat diajukan untuk pesanan yang sudah lunas dan belum disewa.');
        }

        return $order->cancellationRequests()->where('status', CancellationStatus::Pending)->exists()
            ? Response::deny('Pengajuan pembatalan untuk pesanan ini masih menunggu keputusan.')
            : Response::allow();
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
