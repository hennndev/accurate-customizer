<?php

namespace App\Modules;

use App\Modules\Contracts\ModuleHandler;
use App\Modules\Handlers\BaseHandler;
use App\Modules\Handlers\BankTransferHandler;
use App\Modules\Handlers\JournalVoucherHandler;
use App\Modules\Handlers\CustomerHandler;
use App\Modules\Handlers\DeliveryOrderHandler;
use App\Modules\Handlers\ItemTransferHandler;
use App\Modules\Handlers\PurchaseInvoiceHandler;
use App\Modules\Handlers\PurchaseOrderHandler;
use App\Modules\Handlers\PurchasePaymentHandler;
use App\Modules\Handlers\PurchaseRequisitionHandler;
use App\Modules\Handlers\PurchaseReturnHandler;
use App\Modules\Handlers\ReceiveItemHandler;
use App\Modules\Handlers\SalesInvoiceHandler;
use App\Modules\Handlers\SalesOrderHandler;
use App\Modules\Handlers\SalesQuotationHandler;
use App\Modules\Handlers\SalesReceiptHandler;
use App\Modules\Handlers\SalesReturnHandler;

class ModuleManager
{
    public static function forSlug(string $slug): ModuleHandler
    {
        return match ($slug) {
            'bank-transfer' => new BankTransferHandler(),
            'journal-voucher' => new JournalVoucherHandler(),
            'customer' => new CustomerHandler(),
            'item-transfer' => new ItemTransferHandler(),
            'purchase-invoice' => new PurchaseInvoiceHandler(),
            'purchase-order' => new PurchaseOrderHandler(),
            'purchase-payment' => new PurchasePaymentHandler(),
            'purchase-requisition' => new PurchaseRequisitionHandler(),
            'purchase-return' => new PurchaseReturnHandler(),
            'receive-item' => new ReceiveItemHandler(),
            'sales-invoice' => new SalesInvoiceHandler(),
            'sales-order' => new SalesOrderHandler(),
            'sales-quotation' => new SalesQuotationHandler(),
            'sales-receipt' => new SalesReceiptHandler(),
            'sales-return' => new SalesReturnHandler(),
            'delivery-order' => new DeliveryOrderHandler(),
            default => new BaseHandler(),
        };
    }

    public static function forEndpoint(string $endpoint): ModuleHandler
    {
        if (str_contains($endpoint, '/bank-transfer/')) {
            return new BankTransferHandler();
        }
        if (str_contains($endpoint, '/journal-voucher/')) {
            return new JournalVoucherHandler();
        }
        if (str_contains($endpoint, '/customer/')) {
            return new CustomerHandler();
        }
        if (str_contains($endpoint, '/item-transfer/')) {
            return new ItemTransferHandler();
        }
        if (str_contains($endpoint, '/purchase-invoice/')) {
            return new PurchaseInvoiceHandler();
        }
        if (str_contains($endpoint, '/purchase-order/')) {
            return new PurchaseOrderHandler();
        }
        if (str_contains($endpoint, '/purchase-payment/')) {
            return new PurchasePaymentHandler();
        }
        if (str_contains($endpoint, '/purchase-requisition/')) {
            return new PurchaseRequisitionHandler();
        }
        if (str_contains($endpoint, '/purchase-return/')) {
            return new PurchaseReturnHandler();
        }
        if (str_contains($endpoint, '/receive-item/')) {
            return new ReceiveItemHandler();
        }
        if (str_contains($endpoint, '/sales-invoice/')) {
            return new SalesInvoiceHandler();
        }
        if (str_contains($endpoint, '/sales-order/')) {
            return new SalesOrderHandler();
        }
        if (str_contains($endpoint, '/sales-quotation/')) {
            return new SalesQuotationHandler();
        }
        if (str_contains($endpoint, '/sales-receipt/')) {
            return new SalesReceiptHandler();
        }
        if (str_contains($endpoint, '/sales-return/')) {
            return new SalesReturnHandler();
        }
        if (str_contains($endpoint, '/delivery-order/')) {
            return new DeliveryOrderHandler();
        }
        return new BaseHandler();
    }
}
