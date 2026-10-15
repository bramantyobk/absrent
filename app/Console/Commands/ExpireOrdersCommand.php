<?php

namespace App\Console\Commands;

use App\Actions\Orders\ExpireOrders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire')]
#[Description('Batalkan pesanan Pending yang tidak dikonfirmasi dan gagalkan transaksi yang melewati batas verifikasi')]
class ExpireOrdersCommand extends Command
{
    public function handle(ExpireOrders $expireOrders): int
    {
        $result = $expireOrders->handle();

        $this->components->info("{$result['cancelled']} pesanan dibatalkan, {$result['failed']} pesanan digagalkan.");

        return self::SUCCESS;
    }
}
