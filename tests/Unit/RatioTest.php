<?php

use App\Support\Ratio;

it('computes exact percentages with half-up rounding', function () {
    expect(Ratio::percent(1, 3))->toBe('33.33')
        ->and(Ratio::percent(2, 3))->toBe('66.67')
        ->and(Ratio::percent(8, 10))->toBe('80.00')
        ->and(Ratio::percent(0, 12))->toBe('0.00')
        ->and(Ratio::percent(1, 12))->toBe('8.33')
        ->and(Ratio::percent(5, 0))->toBeNull();
});

it('computes exact averages and tolerates missing totals', function () {
    expect(Ratio::average('10', 3))->toBe('3.33')
        ->and(Ratio::average('15.00', 10))->toBe('1.50')
        ->and(Ratio::average('2.005', 1))->toBe('2.01')
        ->and(Ratio::average(null, 5))->toBeNull()
        ->and(Ratio::average('10', 0))->toBeNull();
});
