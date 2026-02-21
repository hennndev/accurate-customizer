<?php

namespace App\Modules\Handlers;

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
        } 
    }
}
