<?php

namespace App\Modules\Handlers;

class DeliveryOrderHandler extends BaseHandler
{
    public function transformDetail(array &$detailData, array $sharedContext, array $meta = []): void
    {
        // if (isset($detailData['number'])) {
        //     unset($detailData['number']);
        // }
    }
}
