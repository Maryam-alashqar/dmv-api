<?php

namespace App\Dto;

use Illuminate\Support\Carbon;

final readonly class VerifiedAppleTransaction
{
    public function __construct(
        public string $transactionId,
        public ?string $originalTransactionId,
        public string $productId,
        public Carbon $purchaseDate,
        public string $environment,
        public ?string $appAccountToken,
        public ?Carbon $revocationDate,
        public string $signedTransactionInfo,
    ) {}
}
