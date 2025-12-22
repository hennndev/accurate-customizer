<?php

namespace App\Modules\Handlers;

use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;

class BankTransferHandler extends BaseHandler
{
    public function transformDetail(array &$detailData, array $sharedContext, array $meta = []): void
    {
        if (isset($detailData['branchId']) && isset($detailData['branch']['id'])) {
            $branchId = $detailData['branchId'];
            if ($branchId == ($detailData['branch']['id'] ?? null) && isset($detailData['branch']['name'])) {
                $branchName = $detailData['branch']['name'];
                unset($detailData['branchId']);
                $detailData['branchName'] = $branchName;
            } else {
                Log::warning('BANK_TRANSFER_BRANCH_MISMATCH', [
                    'item_id' => $meta['itemId'] ?? null,
                    'branch_id' => $branchId,
                    'branch_object_id' => $detailData['branch']['id'] ?? null,
                ]);
            }
        }
    }
}
