<?php

namespace Tests\Feature\Loyalty;

use App\Exceptions\DomainException;
use ReflectionClass;
use Tests\TestCase;

/**
 * Phase 10 (LOY-21, M-10): every loyalty string exists in all five locales.
 * The checks are discovery based, so keys and exception classes added by
 * later Phase 10 plans are covered without editing this file.
 */
class LoyaltyLocaleTest extends TestCase
{
    private const LOCALES = ['en', 'ar', 'fr', 'tr', 'es'];

    /** @return array<string, string> dot path => value, for every leaf of a locale's custom.php */
    private function flatten(string $locale): array
    {
        $tree = require base_path("lang/{$locale}/custom.php");
        $flat = [];
        $walk = function (array $node, string $prefix) use (&$walk, &$flat): void {
            foreach ($node as $key => $value) {
                $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
                is_array($value) ? $walk($value, $path) : $flat[$path] = (string) $value;
            }
        };
        $walk($tree, '');

        return $flat;
    }

    /** @return array<string, array<string, string>> locale => (loyalty dot path => value) */
    private function loyaltyKeysByLocale(): array
    {
        $byLocale = [];
        foreach (self::LOCALES as $locale) {
            $byLocale[$locale] = array_filter(
                $this->flatten($locale),
                fn (string $path) => str_contains($path, 'loyalty'),
                ARRAY_FILTER_USE_KEY,
            );
        }

        return $byLocale;
    }

    /** @return list<class-string<DomainException>> */
    private function loyaltyExceptions(): array
    {
        $classes = [];
        foreach (glob(app_path('Exceptions/Loyalty*Exception.php')) as $file) {
            $classes[] = 'App\\Exceptions\\'.basename($file, '.php');
        }

        return $classes;
    }

    public function test_loyalty_keys_are_identical_across_the_five_locales(): void
    {
        $byLocale = $this->loyaltyKeysByLocale();
        $union = array_unique(array_merge(...array_map('array_keys', array_values($byLocale))));

        $this->assertNotEmpty($union, 'No loyalty lang keys found at all.');

        $missing = [];
        foreach (self::LOCALES as $locale) {
            $absent = array_values(array_diff($union, array_keys($byLocale[$locale])));
            if ($absent !== []) {
                $missing[$locale] = $absent;
            }
        }

        $this->assertSame([], $missing, 'Loyalty lang keys missing per locale: '.json_encode($missing));
    }

    public function test_every_loyalty_exception_resolves_a_translation_in_all_five_locales(): void
    {
        $exceptions = $this->loyaltyExceptions();
        $this->assertNotEmpty($exceptions, 'No Loyalty*Exception classes found.');

        foreach ($exceptions as $class) {
            $exception = (new ReflectionClass($class))->newInstance('message');

            $this->assertInstanceOf(DomainException::class, $exception);
            $this->assertSame(422, $exception->statusCode(), $class);

            $key = 'custom.errors.'.$exception->errorCode();
            foreach (self::LOCALES as $locale) {
                $this->assertNotSame($key, trans($key, [], $locale), "{$class} has no {$locale} string for {$key}");
            }
        }
    }

    public function test_the_eight_error_codes_are_exactly_the_contract(): void
    {
        $codes = array_map(
            fn (string $class) => (new ReflectionClass($class))->newInstance('m')->errorCode(),
            $this->loyaltyExceptions(),
        );
        sort($codes);

        $expected = [
            'loyalty_adjustment_invalid',
            'loyalty_below_minimum',
            'loyalty_discount_conflict',
            'loyalty_insufficient_points',
            'loyalty_over_cap',
            'loyalty_program_inactive',
            'loyalty_reward_unavailable',
            'loyalty_voucher_invalid',
        ];

        $this->assertSame($expected, array_values(array_intersect($expected, $codes)), 'A contract error code is missing.');
    }

    public function test_arabic_loyalty_values_contain_arabic_script(): void
    {
        foreach ($this->loyaltyKeysByLocale()['ar'] as $path => $value) {
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $value, "ar value for {$path} has no Arabic script");
        }
    }

    public function test_loyalty_error_strings_are_not_english_copies(): void
    {
        $byLocale = $this->loyaltyKeysByLocale();

        foreach ($byLocale['en'] as $path => $english) {
            if (! str_starts_with($path, 'errors.')) {
                continue;
            }
            foreach (['ar', 'fr', 'tr', 'es'] as $locale) {
                $this->assertNotSame($english, $byLocale[$locale][$path] ?? null, "{$locale} {$path} is an English copy");
            }
        }
    }
}
