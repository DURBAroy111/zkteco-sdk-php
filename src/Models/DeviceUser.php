<?php

namespace DurbaRoy\Zkteco\Models;

class DeviceUser
{
    public function __construct(
        public int    $pin,         // internal numeric PIN
        public int    $privilege,   // 0 = normal user, 14 = admin, etc.
        public string $password,    // max 8 chars (device limitation)
        public string $name,        // up to 24 chars
        public ?string $cardNumber = null, // optional
        public ?string $userId     = null  // external ID used in your system
    ) {}
}
