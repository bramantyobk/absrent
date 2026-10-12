<?php

namespace App\Http\Requests;

class UpdateBankAccountRequest extends BankAccountRequest
{
    protected function ability(): string
    {
        return 'update';
    }
}
