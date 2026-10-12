<?php

namespace App\Http\Requests;

class StoreBankAccountRequest extends BankAccountRequest
{
    protected function ability(): string
    {
        return 'create';
    }
}
