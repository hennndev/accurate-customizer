<?php

namespace App\Services\Accurate;

use Illuminate\Support\Facades\Log;

class DataCleaner
{
    protected ModuleFieldProvider $moduleFieldProvider;
    protected NumberMappingManager $numberMappingManager;

    public function __construct(
        ModuleFieldProvider $moduleFieldProvider,
        NumberMappingManager $numberMappingManager
    ) {
        $this->moduleFieldProvider = $moduleFieldProvider;
        $this->numberMappingManager = $numberMappingManager;
    }

    public function cleanDataItem(array $item, string $endpoint = '', bool $isSubItem = false): array
    {
        // Only apply handler transform at the top (header) level.
        // Recursive calls for detail sub-items skip this — detail lines are
        // handled by cleanDataItem's own transformation logic below.
        if (!$isSubItem) {
            // Save source id before transformDetail overwrites $item.
            $injectedId = $item['id'] ?? null;

            $handler = \App\Modules\ModuleManager::forEndpoint($endpoint);
            $sharedContext = [];
            $meta = [];
            $handler->transformDetail($item, $sharedContext, $meta);

            // Restore after handler filtering so cleanDataItem can honour source id rules.
            if ($injectedId !== null) { $item['id'] = $injectedId; }

        }

        $cleaned = [];

        foreach ($item as $key => $value) {
            // ===START SKIP FIELDS===
            // Skip internal marker fields
            if ($key === '_sourceNumber') {
                continue;
            }

            // Skip source id on create flow (Accurate will generate new ID)
            if ($key === 'id') {
                continue;
            }

            if ($key === 'vendorType') {
                continue;
            }
            // In detail sub-items, strip branchId (header-level concern) and
            // optLock (source-system concurrency field Accurate doesn't need in lines).
            if ($isSubItem && ($key === 'branchId' || $key === 'optLock')) {
                continue;
            }
            if ($key === 'itemId' && str_contains($endpoint, 'bill-of-material')) {
                continue;
            }
            if ($key === 'transactionType' && str_contains($endpoint, 'journal-voucher')) {
                continue;
            }
            if ($key === 'locationId' && str_contains($endpoint, 'warehouse')) {
                continue;
            }
            if ($key === 'branchId' && str_contains($endpoint, 'stock-opname-order')) {
                continue;
            }
            if (str_contains($endpoint, '/tax/') && ($key === 'salesTaxGlAccountId' || $key === 'purchaseTaxGlAccountId')) {
                continue;
            }
            if ($key === 'number' && (
                str_contains($endpoint, 'delivery-order') ||
                str_contains($endpoint, 'purchase-invoice') ||
                str_contains($endpoint, 'purchase-order') ||
                str_contains($endpoint, 'purchase-payment') ||
                str_contains($endpoint, 'purchase-requisition') ||
                str_contains($endpoint, 'purchase-return') ||
                str_contains($endpoint, 'sales-invoice') ||
                str_contains($endpoint, 'sales-order') ||
                str_contains($endpoint, 'sales-quotation') ||
                str_contains($endpoint, 'sales-receipt') ||
                str_contains($endpoint, 'sales-return') ||
                str_contains($endpoint, 'receive-item') ||
                str_contains($endpoint, 'job-order') ||
                str_contains($endpoint, 'item-transfer')
            )) {
                continue;
            }

            if ($key === 'apAccountId') {
                continue;
            }
            if ($key === 'purchaseOrderDetailId') {
                continue;
            }
            if ($key === 'salesOrderDetailId') {
                continue;
            }
            if ($key === 'vendorTaxType' && str_contains($endpoint, 'purchase-invoice')) {
                continue;
            }
            // ===END SKIP FIELDS===

            // ===START TRANSFORM FIELDS===
            if (str_contains($endpoint, 'item')) {
                if ($key == "itemCategory" && is_array($value)) {
                    if (isset($value['name'])) {
                        $cleaned['itemCategoryName'] = $value['name'];
                    }
                    continue;
                }
            }
            if (str_contains($endpoint, 'stock-opname-order')) {
                if ($key == "itemCategoryList" && is_array($value)) {
                    $cleaned['itemCategoryListName'] = $value;
                    continue;
                }
            }
            if (str_contains($endpoint, 'material-slip')) {
                if ($key == "branch" && is_array($value)) {
                    if (isset($value['name'])) {
                        $cleaned['branchName'] = $value['name'];
                    }
                    continue;
                }
                if ($key == "workOrder" && is_array($value)) {
                    if (isset($value['number'])) {
                        $cleaned['workOrderNumber'] = $value['number'];
                    }
                    continue;
                }
            }
            if ($key === 'vendor' && is_array($value) && (str_contains($endpoint, 'purchase-order') || str_contains($endpoint, 'purchase-invoice') || str_contains($endpoint, 'purchase-payment') || str_contains($endpoint, 'purchase-return') || str_contains($endpoint, 'receive-item'))) {
                if (isset($value['vendorNo'])) {
                    $cleaned['vendorNo'] = $value['vendorNo'];
                }
                continue;
            }
            if ($key === 'customer' && is_array($value) && (str_contains($endpoint, 'sales-order') || str_contains($endpoint, 'sales-invoice') || str_contains($endpoint, 'sales-quotation') || str_contains($endpoint, 'sales-receipt') || str_contains($endpoint, 'sales-return') || str_contains($endpoint, 'delivery-order'))) {
                if (isset($value['customerNo'])) {
                    $cleaned['customerNo'] = $value['customerNo'];
                }
                continue;
            }
            if (str_contains($endpoint, 'bank-transfer')) {
                if ($key === 'fromBank' && is_array($value)) {
                    if (isset($value['no'])) {
                        $cleaned['fromBankNo'] = $value['no'];
                    }
                    continue;
                }
                if ($key === 'toBank' && is_array($value)) {
                    if (isset($value['no'])) {
                        $cleaned['toBankNo'] = $value['no'];
                    }
                    continue;
                }
            }
            if ($key === 'expensePayable' && is_array($value) && str_contains($endpoint, 'expense')) {
                if (isset($value['no'])) {
                    $cleaned['expensePayableNo'] = $value['no'];
                }
                continue;
            }
            if ($key === 'bank' && is_array($value) && (str_contains($endpoint, 'sales-receipt') || str_contains($endpoint, 'purchase-payment'))) {
                if (isset($value['no'])) {
                    $cleaned['bankNo'] = $value['no'];
                }
                continue;
            }
            if ($key === 'fromItemTransfer' && is_array($value) && str_contains($endpoint, 'item-transfer')) {
                if (isset($value['number'])) {
                    $cleaned['fromItemTransferNo'] = $value['number'];
                }
                continue;
            }
            if ($key === 'invoice' && is_array($value) && (str_contains($endpoint, 'purchase-return') || str_contains($endpoint, 'sales-return'))) {
                if (isset($value['number'])) {
                    if (str_contains($endpoint, 'purchase-return')) {
                        $cleaned['invoiceNumber'] = $this->numberMappingManager->getMappedNumber(
                            'purchase-invoice',
                            $value['number']
                        );
                    } else {
                        $cleaned['invoiceNumber'] = $this->numberMappingManager->getMappedNumber(
                            'sales-invoice',
                            $value['number']
                        );
                    }
                }
                continue;
            }
            if ($key === 'order' && is_array($value) && str_contains($endpoint, 'stock-opname-result')) {
                if (isset($value['number'])) {
                    $cleaned['orderNumber'] = $value['number'];
                }
                continue;
            }
            if ($key === 'jobOrder' && is_array($value) && str_contains($endpoint, 'roll-over')) {
                if (isset($value['number'])) {
                    $cleaned['jobOrderNumber'] = $value['number'];
                }
                continue;
            }
            if ($key === 'billOfMaterial' && is_array($value) && str_contains($endpoint, 'work-order')) {
                if (isset($value['number'])) {
                    $cleaned['billOfMaterialNo'] = $value['number'];
                }
                continue;
            }
            if ($key === 'manufactureOrder' && is_array($value) && str_contains($endpoint, 'work-order')) {
                if (isset($value['number'])) {
                    $cleaned['manufactureOrderNo'] = $value['number'];
                }
                continue;
            }
            if ($key === 'warehouse' && is_array($value) && str_contains($endpoint, 'stock-opname-order')) {
                if (isset($value['name'])) {
                    $cleaned['warehouseName'] = $value['name'];
                }
                continue;
            }
            if ($key === 'item' && is_array($value) && str_contains($endpoint, 'bill-of-material')) {
                if (isset($value['no'])) {
                    $cleaned['itemNo'] = $value['no'];
                }
                continue;
            }
            if ($key === 'paymentTerm' && is_array($value)) {
                if (isset($value['name'])) {
                    $cleaned['paymentTermName'] = $value['name'];
                }
                continue;
            }
            // ===END TRANSFORM FIELDS===

            if (($key === 'npwpNo' || $key === 'wpNumber') && is_string($value)) {
                $value = preg_replace('/[^0-9]/', '', $value);
                if ($value === '') {
                    continue;
                }
                if (strlen($value) < 16) {
                    $value = str_pad($value, 16, '0', STR_PAD_RIGHT);
                }
                if (strlen($value) > 16) {
                    $value = substr($value, 0, 16);
                }
            }
            if ($value === null) {
                continue;
            }
            if (str_ends_with($key, 'Id') && $value === 0) {
                continue;
            }

            if ($value === '') {
                continue;
            }

            // ===TRANSFORM ARRAY ITEMS===
            if (is_array($value)) {
                if (empty($value)) {
                    continue;
                }

                $cleanedArray = [];
                foreach ($value as $subKey => $subValue) {
                    if (is_array($subValue)) {
                        // Detail sub-items should never carry their source-system `id`.
                        // Only the header `id` (set by the inject loop) is needed for UPDATE.
                        // Always pass $isUpdate=false so sub-item `id` fields are stripped.
                        $cleanedSubItem = $this->cleanDataItem($subValue, $endpoint, true);
                        if (!empty($cleanedSubItem)) {
                            if ($key === 'detailItem' && (
                                str_contains($endpoint, 'purchase-order') ||
                                str_contains($endpoint, 'purchase-invoice') ||
                                str_contains($endpoint, 'purchase-return') ||
                                str_contains($endpoint, 'receive-item') ||
                                str_contains($endpoint, 'sales-order') ||
                                str_contains($endpoint, 'sales-invoice') ||
                                str_contains($endpoint, 'job-order') ||
                                str_contains($endpoint, 'sales-quotation') ||
                                str_contains($endpoint, 'sales-return') ||
                                str_contains($endpoint, 'delivery-order') ||
                                str_contains($endpoint, 'material-slip') ||
                                str_contains($endpoint, 'finished-good-slip') ||
                                str_contains($endpoint, 'vendor-price') ||
                                str_contains($endpoint, 'purchase-requisition') ||
                                str_contains($endpoint, 'stock-opname-result') ||
                                str_contains($endpoint, 'item-transfer'))) {
                                if (isset($cleanedSubItem['item']['no'])) {
                                    $cleanedSubItem['itemNo'] = $cleanedSubItem['item']['no'];
                                    unset($cleanedSubItem['item']);
                                }
                                if (isset($cleanedSubItem['warehouse']['name'])) {
                                    $cleanedSubItem['warehouseName'] = $cleanedSubItem['warehouse']['name'];
                                    unset($cleanedSubItem['warehouse']);
                                }
                                if (isset($cleanedSubItem['itemUnit']['name'])) {
                                    $cleanedSubItem['itemUnitName'] = $cleanedSubItem['itemUnit']['name'];
                                    unset($cleanedSubItem['itemUnit']);
                                    unset($cleanedSubItem['itemUnitId']);
                                }
                                if (isset($cleanedSubItem['purchaseOrder']['number'])) {
                                    $cleanedSubItem['purchaseOrderNumber'] = $this->numberMappingManager->getMappedNumber(
                                        'purchase-order',
                                        $cleanedSubItem['purchaseOrder']['number']
                                    );
                                    unset($cleanedSubItem['purchaseOrder']);
                                }
                                // Always strip purchaseOrderId — Accurate resolves the PO reference
                                // via purchaseOrderNumber, not the source-system integer ID.
                                unset($cleanedSubItem['purchaseOrderId']);
                                if (isset($cleanedSubItem['salesOrder']['number'])) {
                                    $cleanedSubItem['salesOrderNumber'] = $this->numberMappingManager->getMappedNumber(
                                        'sales-order',
                                        $cleanedSubItem['salesOrder']['number']
                                    );
                                    unset($cleanedSubItem['salesOrder']);
                                }
                                if (isset($cleanedSubItem['salesQuotation']['number'])) {
                                    $cleanedSubItem['salesQuotationNumber'] = $this->numberMappingManager->getMappedNumber(
                                        'sales-quotation',
                                        $cleanedSubItem['salesQuotation']['number']
                                    );
                                    unset($cleanedSubItem['salesQuotation']);
                                }

                                if (str_contains($endpoint, 'purchase-invoice')) {
                                    $sourceReceiveItemNumber = $this->resolvePurchaseInvoiceReceiveItemNumber(
                                        $subValue,
                                        $cleanedSubItem
                                    );

                                    if ($sourceReceiveItemNumber !== null && $sourceReceiveItemNumber !== '') {
                                        $cleanedSubItem['receiveItemNumber'] = $this->numberMappingManager->getMappedNumber(
                                            'receive-item',
                                            (string) $sourceReceiveItemNumber
                                        );
                                    }

                                    unset($cleanedSubItem['receiveItem']);
                                    unset($cleanedSubItem['receiveItemId']);
                                    unset($cleanedSubItem['receiveItemDetail']);
                                }
                            }

                            if ($key === 'detailItem' && str_contains($endpoint, 'item-adjustment')) {
                                $adjustmentItem = [];
                                if (isset($cleanedSubItem['item']['no'])) {
                                    $adjustmentItem['itemNo'] = $cleanedSubItem['item']['no'];
                                }
                                if (isset($cleanedSubItem['itemAdjustmentType'])) {
                                    $adjustmentItem['itemAdjustmentType'] = $cleanedSubItem['itemAdjustmentType'];
                                }
                                if (isset($cleanedSubItem['unitCost'])) {
                                    $adjustmentItem['unitCost'] = $cleanedSubItem['unitCost'];
                                }
                                if (isset($cleanedSubItem['quantity'])) {
                                    $adjustmentItem['quantity'] = $cleanedSubItem['quantity'];
                                }
                                $cleanedSubItem = $adjustmentItem;
                            }

                            if ($key === 'detailSerialNumber' && (str_contains($endpoint, '/item/') || str_contains($endpoint, 'job-order') || str_contains($endpoint, 'item-transfer') || str_contains($endpoint, 'purchase-invoice') || str_contains($endpoint, 'receive-item') || str_contains($endpoint, 'sales-invoice'))) {
                                if (isset($cleanedSubItem['serialNumber']['number'])) {
                                    $cleanedSubItem['serialNumberNo'] = $cleanedSubItem['serialNumber']['number'];
                                    unset($cleanedSubItem['serialNumber']);
                                } elseif (isset($cleanedSubItem['serialNumber']['no'])) {
                                    $cleanedSubItem['serialNumberNo'] = $cleanedSubItem['serialNumber']['no'];
                                    unset($cleanedSubItem['serialNumber']);
                                }
                            }

                            if ($key === 'detailAccount' && str_contains($endpoint, 'expense')) {
                                if (isset($cleanedSubItem['account']['no'])) {
                                    $cleanedSubItem['accountNo'] = $cleanedSubItem['account']['no'];
                                    unset($cleanedSubItem['account']);
                                }
                            }

                            if ($key === 'detailJournalVoucher' && str_contains($endpoint, 'journal-voucher')) {
                                $amount = $cleanedSubItem['amount'] ?? 0;
                                if ($amount < 1) {
                                    continue;
                                }
                                if (isset($cleanedSubItem['glAccount']['no'])) {
                                    $cleanedSubItem['accountNo'] = $cleanedSubItem['glAccount']['no'];
                                    unset($cleanedSubItem['glAccount']);
                                }
                                if (isset($cleanedSubItem['vendor']['vendorNo'])) {
                                    $cleanedSubItem['vendorNo'] = $cleanedSubItem['vendor']['vendorNo'];
                                    unset($cleanedSubItem['vendor']);
                                }
                                if (isset($cleanedSubItem['customer']['customerNo'])) {
                                    $cleanedSubItem['customerNo'] = $cleanedSubItem['customer']['customerNo'];
                                    unset($cleanedSubItem['customer']);
                                }
                            }

                            // ===ITEM===
                            if ($key === "detailOpenBalance" && str_contains($endpoint, "item")) {
                                if (isset($cleanedSubItem['warehouse']['name'])) {
                                    $cleanedSubItem['warehouseName'] = $cleanedSubItem['warehouse']['name'];
                                    unset($cleanedSubItem['warehouse']);
                                }
                            }

                            // ===WORK ORDER===
                            if ($key === 'detailExpense' && (str_contains($endpoint, 'work-order') || str_contains($endpoint, 'bill-of-material') || str_contains($endpoint, 'purchase-invoice') || str_contains($endpoint, 'purchase-order'))) {
                                if (isset($cleanedSubItem['item']['no'])) {
                                    $cleanedSubItem['itemNo'] = $cleanedSubItem['item']['no'];
                                    unset($cleanedSubItem['item']);
                                }
                                if (isset($cleanedSubItem['account']['no'])) {
                                    $cleanedSubItem['accountNo'] = $cleanedSubItem['account']['no'];
                                    unset($cleanedSubItem['account']);
                                }
                                if (isset($cleanedSubItem['purchaseOrder']['number'])) {
                                    $cleanedSubItem['purchaseOrderNumber'] = $this->numberMappingManager->getMappedNumber(
                                        'purchase-order',
                                        $cleanedSubItem['purchaseOrder']['number']
                                    );
                                    unset($cleanedSubItem['purchaseOrder']);
                                }
                            }
                            if ($key === 'detailDownPayment' && (str_contains($endpoint, 'purchase-invoice') || str_contains($endpoint, 'sales-invoice'))) {
                                if (isset($cleanedSubItem['invoice']['number'])) {
                                    $moduleSlug = str_contains($endpoint, 'purchase-invoice') ? 'purchase-invoice' : 'sales-invoice';
                                    $cleanedSubItem['invoiceNumber'] = $this->numberMappingManager->getMappedNumber(
                                        $moduleSlug,
                                        $cleanedSubItem['invoice']['number']
                                    );
                                }
                            }

                            if ($key === 'detailMaterial' && (str_contains($endpoint, 'work-order') || str_contains($endpoint, 'bill-of-material'))) {
                                if (isset($cleanedSubItem['item']['no'])) {
                                    $cleanedSubItem['itemNo'] = $cleanedSubItem['item']['no'];
                                    unset($cleanedSubItem['item']);
                                }
                            }
                            if ($key === 'detailExtraFinishGood' && (str_contains($endpoint, 'work-order') || str_contains($endpoint, 'bill-of-material'))) {
                                if (isset($cleanedSubItem['item']['no'])) {
                                    $cleanedSubItem['itemNo'] = $cleanedSubItem['item']['no'];
                                    unset($cleanedSubItem['item']);
                                }
                            }
                            if ($key === 'detailProcess' && (str_contains($endpoint, 'work-order') || str_contains($endpoint, 'bill-of-material'))) {
                                if (isset($cleanedSubItem['processCategory']['name'])) {
                                    $cleanedSubItem['processCategoryName'] = $cleanedSubItem['processCategory']['name'];
                                    unset($cleanedSubItem['processCategory']);
                                }
                            }

                            if ($key === 'detailInvoice' && (str_contains($endpoint, 'purchase-payment') || str_contains($endpoint, 'sales-receipt'))) {
                                if (isset($cleanedSubItem['invoice']['number'])) {
                                    if (str_contains($endpoint, 'purchase-payment')) {
                                        $cleanedSubItem['invoiceNo'] = $this->numberMappingManager->getMappedNumber(
                                            'purchase-invoice',
                                            $cleanedSubItem['invoice']['number']
                                        );
                                    } else {
                                        $cleanedSubItem['invoiceNo'] = $this->numberMappingManager->getMappedNumber(
                                            'sales-invoice',
                                            $cleanedSubItem['invoice']['number']
                                        );
                                    }
                                    unset($cleanedSubItem['invoiceId']);
                                    unset($cleanedSubItem['invoice']);
                                }

                                if (isset($cleanedSubItem['detailDiscount']) && is_array($cleanedSubItem['detailDiscount'])) {
                                    foreach ($cleanedSubItem['detailDiscount'] as $discountKey => $discount) {
                                        if (is_array($discount) && isset($discount['account']['no'])) {
                                            $cleanedSubItem['detailDiscount'][$discountKey]['accountNo'] = $discount['account']['no'];
                                            unset($cleanedSubItem['detailDiscount'][$discountKey]['account']);
                                        }
                                    }
                                }
                            }
                            $cleanedArray[] = $cleanedSubItem;
                        }
                    } else {
                        if ($subKey === 'id' || $subKey === 'vendorType') {
                            continue;
                        }
                        if (
                            $subValue !== null && $subValue !== '' &&
                            !(str_ends_with($subKey, 'Id') && $subValue === 0)
                        ) {
                            $cleanedArray[$subKey] = $subValue;
                        }
                    }
                }

                if (!empty($cleanedArray)) {
                    $cleaned[$key] = $cleanedArray;
                }
                continue;
            }
            // TRANSFORM ARRAY ITEMS===

            $cleaned[$key] = $value;
        }
        return $cleaned;
    }

    private function resolvePurchaseInvoiceReceiveItemNumber(array $rawSubValue, array $cleanedSubItem): ?string
    {
        $candidates = [
            $rawSubValue['receiveItem']['number'] ?? null,
            $rawSubValue['receiveItem']['no'] ?? null,
            $rawSubValue['receiveItemDetail']['receiveItem']['number'] ?? null,
            $rawSubValue['receiveItemDetail']['receiveItem']['no'] ?? null,
            $rawSubValue['receiveItemNumber'] ?? null,
            $cleanedSubItem['receiveItemNumber'] ?? null,
            $cleanedSubItem['receiveItem']['number'] ?? null,
            $cleanedSubItem['receiveItem']['no'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return (string) $candidate;
            }
        }

        return null;
    }
}
