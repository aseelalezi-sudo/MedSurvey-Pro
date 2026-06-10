<?php

namespace Tests\Unit;

use App\Support\Privacy;
use PHPUnit\Framework\TestCase;

class PrivacyTest extends TestCase
{
    public function test_phone_mask_keeps_only_minimal_visible_digits(): void
    {
        $this->assertSame('77*****45', Privacy::maskPhone('777123445'));
        $this->assertSame('05******67', Privacy::maskPhone('055-123-4567'));
        $this->assertSame('', Privacy::maskPhone(null));
    }
}
