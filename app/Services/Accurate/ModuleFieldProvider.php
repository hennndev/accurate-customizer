<?php

namespace App\Services\Accurate;

class ModuleFieldProvider
{
    private array $moduleFieldMap = [
        'vendor' => 'vendorNo',
        'customer' => 'customerNo',
        'item' => 'no',
        'glaccount' => 'no',
        'employee' => 'no',
        'tax' => 'no',
        'project' => 'no',
        'warehouse' => 'id',
        'branch' => 'id',
        'department' => 'id',
        'sales-order' => 'number',
        'purchase-order' => 'number',
        'sales-invoice' => 'number',
        'purchase-invoice' => 'number',
        'delivery-order' => 'number',
        'receive-item' => 'receiveNumber',
        'sales-quotation' => 'number',
        'purchase-requisition' => 'number',
        'sales-return' => 'number',
        'purchase-return' => 'number',
        'sales-receipt' => 'number',
        'purchase-payment' => 'number',
        'item-transfer' => 'number',
        'item-adjustment' => 'number',
        'job-order' => 'number',
        'work-order' => 'number',
        'item-category' => 'id',
        'unit' => 'id',
        'vendor-category' => 'id',
        'vendor-claim' => 'id',
        'vendor-price' => 'id',
        'customer-category' => 'id',
        'currency' => 'id',
        'fob' => 'id',
        'data-classification' => 'id',
        'price-category' => 'id',
        'bill-of-material' => 'id',
    ];

    public function getNumberFieldForModule(string $module, array $firstItem): ?string
    {
        if (isset($this->moduleFieldMap[$module])) {
            return $this->moduleFieldMap[$module];
        }

        $possibleFields = ['no', 'vendorNo', 'customerNo', 'number'];

        foreach ($possibleFields as $field) {
            if (isset($firstItem[$field]) && !empty($firstItem[$field])) {
                return $field;
            }
        }

        return null;
    }
}
