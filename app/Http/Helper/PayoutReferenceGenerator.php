<?php

namespace App\Http\Helper;

class PayoutReferenceGenerator
{
    public static function generate(string $projectType): array
    {
        $systemId = OrderIdGenerator::generate();

        return [
            'id' => $systemId,
            'reference' => $projectType.'-'.$systemId,
        ];
    }
}
