<?php

namespace App\Http\Requests;

class StoreEquipmentItemRequest extends EquipmentItemRequest
{
    protected function ability(): string
    {
        return 'create';
    }
}
