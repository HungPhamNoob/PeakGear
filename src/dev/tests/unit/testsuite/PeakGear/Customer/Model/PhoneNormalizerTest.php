<?php
declare(strict_types=1);

namespace PeakGear\Customer\Model;

use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    /**
     * @dataProvider validPhoneProvider
     */
    public function testValidPhoneIsNormalized(string $input, string $expected): void
    {
        $normalizer = new PhoneNormalizer();

        self::assertTrue($normalizer->isValid($input));
        self::assertSame($expected, $normalizer->normalize($input));
    }

    public function validPhoneProvider(): array
    {
        return [
            'local mobile' => ['0912345678', '0912345678'],
            'formatted mobile' => ['0912 345 678', '0912345678'],
            'international mobile' => ['+84 912 345 678', '0912345678'],
            'eleven digit local number' => ['01234567890', '01234567890'],
        ];
    }

    /**
     * @dataProvider invalidPhoneProvider
     */
    public function testInvalidPhoneIsRejected(string $input): void
    {
        self::assertFalse((new PhoneNormalizer())->isValid($input));
    }

    public function invalidPhoneProvider(): array
    {
        return [
            'empty' => [''],
            'too short' => ['091234567'],
            'too long' => ['091234567890'],
            'missing local prefix' => ['912345678'],
            'invalid country code' => ['+1 912 345 678'],
            'all zeros' => ['0000000000'],
        ];
    }
}
