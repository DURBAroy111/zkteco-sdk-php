<?php

namespace DurbaRoy\Zkteco\Models;

class FingerprintTemplate
{
    public function __construct(
        public string $userId,
        public int $fingerIndex,
        public string $templateData, // binary or base64 encoded
        public int $flag = 1
    ) {}
}
