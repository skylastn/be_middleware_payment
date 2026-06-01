<?php

namespace App\Repository\Payment;

use App\Model\Entity\PaymentCategory;
use App\Repository\BaseRepository;

class PaymentCategoryRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return PaymentCategory::class;
    }
}
