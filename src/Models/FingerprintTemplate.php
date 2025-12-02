<?php

namespace DurbaRoy\Zkteco\Models;

class FingerprintTemplate
{
    public function __construct(
        public int    $pin,          // internal PIN that this template belongs to
        public int    $fingerIndex,  // 0-9
        public bool   $valid,        // true if template is valid
        public string $templateData  // raw binary template bytes
    ) {}
}
