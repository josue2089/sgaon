<?php

namespace Tests\Unit;

use App\Support\MoneyFormat;
use Tests\TestCase;

class MoneyFormatTest extends TestCase
{
    public function test_number_uses_dot_thousands_and_comma_decimals(): void
    {
        $this->assertSame('1.234,56', MoneyFormat::number(1234.56));
        $this->assertSame('10.000,00', MoneyFormat::number(10000));
    }

    public function test_currency_helpers_share_the_same_numeric_format(): void
    {
        $this->assertSame('$1.234,56', MoneyFormat::usd(1234.56));
        $this->assertSame('€1.234,56', MoneyFormat::eur(1234.56));
        $this->assertSame('Bs 1.234,56', MoneyFormat::ves(1234.56));
    }

    public function test_rate_uses_four_decimal_places(): void
    {
        $this->assertSame('36,5432', MoneyFormat::rate(36.54321));
    }

    public function test_raw_uses_dot_decimal_for_machine_values(): void
    {
        $this->assertSame('1234.56', MoneyFormat::raw(1234.56));
    }
}
