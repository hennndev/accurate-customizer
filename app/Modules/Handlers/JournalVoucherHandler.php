<?php

namespace App\Modules\Handlers;

use Illuminate\Support\Facades\Log;
use App\Services\AccurateService;

class JournalVoucherHandler extends BaseHandler
{
    public function transformDetail(array &$detailData, array $sharedContext, array $meta = []): void
    {
        if (!isset($detailData['branchId'])) {
            return;
        }
        $rootBranchId = $detailData['branchId'];
        $foundBranchName = null;

        if (isset($detailData['detailJournalVoucher']) && is_array($detailData['detailJournalVoucher'])) {
            foreach ($detailData['detailJournalVoucher'] as $detail) {
                if (isset($detail['branch']['id']) && $detail['branch']['id'] == $rootBranchId) {
                    if (isset($detail['branch']['name'])) {
                        $foundBranchName = $detail['branch']['name'];
                        break;
                    }
                }
            }
        }

        if ($foundBranchName !== null) {
            unset($detailData['branchId']);
            $detailData['branchName'] = $foundBranchName;
        } else {
            Log::warning('JOURNAL_VOUCHER_BRANCH_NOT_FOUND', [
                'item_id' => $meta['itemId'] ?? null,
                'root_branch_id' => $rootBranchId,
                'detail_count' => count($detailData['detailJournalVoucher'] ?? []),
            ]);
        }
    }
}
