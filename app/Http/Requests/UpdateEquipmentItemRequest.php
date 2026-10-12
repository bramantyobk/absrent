<?php

namespace App\Http\Requests;

class UpdateEquipmentItemRequest extends EquipmentItemRequest
{
    protected function ability(): string
    {
        return 'update';
    }
}
