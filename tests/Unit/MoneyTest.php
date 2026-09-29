<?php

use App\Support\Money;

it('parses decimal strings into integer minor units without floats', function () {
    expect(Money::parse('1250.50', 'ngn')->minor)->toBe(125050)
        ->and(Money::parse('1,250', 'NGN')->minor)->toBe(125000)
        ->and(Money::parse('0.5', 'NGN')->minor)->toBe(50)
        ->and(Money::parse('19.99', 'NGN')->minor)->toBe(1999)
        ->and(Money::parse('-3.05', 'NGN')->minor)->toBe(-305);
});

it('rejects malformed amounts', function (string $bad) {
    expect(fn () => Money::parse($bad, 'NGN'))->toThrow(InvalidArgumentException::class);
})->with(['1.234', 'abc', '', '1e5', '1.']);

it('does exact arithmetic where floats would drift', function () {
    $sum = Money::parse('0.10', 'NGN')->add(Money::parse('0.20', 'NGN'));

    expect($sum->minor)->toBe(30)
        ->and($sum->toDecimal())->toBe('0.30')
        ->and(Money::parse('2.50', 'NGN')->multiply(3)->format())->toBe('NGN 7.50')
        ->and(Money::ofMinor(-5, 'NGN')->toDecimal())->toBe('-0.05')
        ->and(Money::ofMinor(123456789, 'NGN')->format())->toBe('NGN 1,234,567.89');
});

it('refuses to mix currencies', function () {
    expect(fn () => Money::ofMinor(1, 'NGN')->add(Money::ofMinor(1, 'USD')))->toThrow(InvalidArgumentException::class);
});
