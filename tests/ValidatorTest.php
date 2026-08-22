<?php
declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use App\Validator;

class ValidatorTest extends TestCase
{
    public function testRequiredValidation(): void
    {
        $v = new Validator(['name' => '']);
        $v->required('name', 'Name');
        $this->assertFalse($v->isValid());
        $this->assertEquals('Name is required.', $v->getFirstError());
    }

    public function testNumericAndMinValidation(): void
    {
        $v = new Validator(['amount' => 'invalid']);
        $v->numeric('amount', 'Amount');
        $this->assertFalse($v->isValid());

        $v2 = new Validator(['amount' => 5]);
        $v2->min('amount', 10, 'Amount');
        $this->assertFalse($v2->isValid());
        $this->assertEquals('Amount must be at least 10.', $v2->getFirstError());
    }
}
