<?php

namespace App\Auth;

interface OtpCodeGenerator
{
    public function generate(): string;
}
