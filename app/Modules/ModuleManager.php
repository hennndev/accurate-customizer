<?php

namespace App\Modules;

use App\Modules\Contracts\ModuleHandler;
use App\Modules\Handlers\BaseHandler;
use App\Modules\Handlers\BankTransferHandler;
use App\Modules\Handlers\BillOfMaterialHandler;
use App\Modules\Handlers\BranchHandler;
use App\Modules\Handlers\CurrencyHandler;
use App\Modules\Handlers\CustomerCategoryHandler;
use App\Modules\Handlers\CustomerHandler;
use App\Modules\Handlers\DepartmentHandler;
use App\Modules\Handlers\DeliveryOrderHandler;
use App\Modules\Handlers\FinishedGoodSlipHandler;
use App\Modules\Handlers\MaterialSlipHandler;
use App\Modules\Handlers\WorkOrderHandler;
use App\Modules\Handlers\ItemHandler;
use App\Modules\Handlers\ItemCategoryHandler;
use App\Modules\Handlers\ItemAdjustmentHandler;
use App\Modules\Handlers\ItemTransferHandler;
use App\Modules\Handlers\JournalVoucherHandler;
use App\Modules\Handlers\DownPaymentPurchaseInvoiceHandler;
use App\Modules\Handlers\DownPaymentSalesInvoiceHandler;
use App\Modules\Handlers\PurchaseInvoiceHandler;
use App\Modules\Handlers\UnitHandler;
use App\Modules\Handlers\VendorCategoryHandler;
use App\Modules\Handlers\VendorClaimHandler;
use App\Modules\Handlers\VendorHandler;
use App\Modules\Handlers\VendorPriceHandler;
use App\Modules\Handlers\WarehouseHandler;
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
            'branch' => new BranchHandler(),
            'currency' => new CurrencyHandler(),
            'customer' => new CustomerHandler(),
            'customer-category' => new CustomerCategoryHandler(),
            'department' => new DepartmentHandler(),
            'journal-voucher' => new JournalVoucherHandler(),
            'item' => new ItemHandler(),
            'item-category' => new ItemCategoryHandler(),
            'item-adjustment' => new ItemAdjustmentHandler(),
            'item-transfer' => new ItemTransferHandler(),
            'purchase-invoice' => new PurchaseInvoiceHandler(),
            'down-payment-purchase-invoice' => new DownPaymentPurchaseInvoiceHandler(),
            'purchase-order' => new PurchaseOrderHandler(),
            'purchase-payment' => new PurchasePaymentHandler(),
            'purchase-requisition' => new PurchaseRequisitionHandler(),
            'purchase-return' => new PurchaseReturnHandler(),
            'receive-item' => new ReceiveItemHandler(),
            'sales-invoice' => new SalesInvoiceHandler(),
            'down-payment-sales-invoice' => new DownPaymentSalesInvoiceHandler(),
            'sales-order' => new SalesOrderHandler(),
            'sales-quotation' => new SalesQuotationHandler(),
            'sales-receipt' => new SalesReceiptHandler(),
            'sales-return' => new SalesReturnHandler(),
            'delivery-order' => new DeliveryOrderHandler(),
            'bill-of-material' => new BillOfMaterialHandler(),
            'finished-good-slip' => new FinishedGoodSlipHandler(),
            'material-slip' => new MaterialSlipHandler(),
            'work-order' => new WorkOrderHandler(),
            'unit' => new UnitHandler(),
            'vendor' => new VendorHandler(),
            'vendor-category' => new VendorCategoryHandler(),
            'vendor-claim' => new VendorClaimHandler(),
            'vendor-price' => new VendorPriceHandler(),
            'warehouse' => new WarehouseHandler(),
            default => new BaseHandler(),
        };
    }

    public static function forEndpoint(string $endpoint): ModuleHandler
    {
        if (str_contains($endpoint, '/bank-transfer/')) {
            return new BankTransferHandler();
        }
        if (str_contains($endpoint, '/branch/')) {
            return new BranchHandler();
        }
        if (str_contains($endpoint, '/currency/')) {
            return new CurrencyHandler();
        }
        if (str_contains($endpoint, '/customer-category/')) {
            return new CustomerCategoryHandler();
        }
        if (str_contains($endpoint, '/customer/')) {
            return new CustomerHandler();
        }
        if (str_contains($endpoint, '/department/')) {
            return new DepartmentHandler();
        }
        if (str_contains($endpoint, '/journal-voucher/')) {
            return new JournalVoucherHandler();
        }
        if (str_contains($endpoint, '/item-category/')) {
            return new ItemCategoryHandler();
        }
        if (str_contains($endpoint, '/item-adjustment/')) {
            return new ItemAdjustmentHandler();
        }
        if (str_contains($endpoint, '/item/')) {
            return new ItemHandler();
        }
        if (str_contains($endpoint, '/item-transfer/')) {
            return new ItemTransferHandler();
        }
        if (str_contains($endpoint, '/purchase-invoice/')) {
            if (str_contains($endpoint, '/down-payment-purchase-invoice/')) {
                return new DownPaymentPurchaseInvoiceHandler();
            }
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
            if (str_contains($endpoint, '/down-payment-sales-invoice/')) {
                return new DownPaymentSalesInvoiceHandler();
            }
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
        if (str_contains($endpoint, '/bill-of-material/')) {
            return new BillOfMaterialHandler();
        }
        if (str_contains($endpoint, '/finished-good-slip/')) {
            return new FinishedGoodSlipHandler();
        }
        if (str_contains($endpoint, '/material-slip/')) {
            return new MaterialSlipHandler();
        }
        if (str_contains($endpoint, '/work-order/')) {
            return new WorkOrderHandler();
        }
        if (str_contains($endpoint, '/unit/')) {
            return new UnitHandler();
        }
        if (str_contains($endpoint, '/vendor-category/')) {
            return new VendorCategoryHandler();
        }
        if (str_contains($endpoint, '/vendor-claim/')) {
            return new VendorClaimHandler();
        }
        if (str_contains($endpoint, '/vendor-price/')) {
            return new VendorPriceHandler();
        }
        if (str_contains($endpoint, '/vendor/')) {
            return new VendorHandler();
        }
        if (str_contains($endpoint, '/warehouse/')) {
            return new WarehouseHandler();
        }
        return new BaseHandler();
    }
}
