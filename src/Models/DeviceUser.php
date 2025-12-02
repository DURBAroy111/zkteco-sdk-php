<?php

namespace DurbaRoy\Zkteco\Models;

class DeviceUser
{
    public function __construct(
        public string $uid,
        public int $role,
        public string $name,
        public string $password = ''
    ) {}
}
