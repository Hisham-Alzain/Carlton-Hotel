<?php

namespace Tests\Unit;

use App\Base\BaseFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class BaseFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('filter_scratch', function ($t) {
            $t->id();
            $t->string('name');
            $t->integer('price');
            $t->string('status');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        DB::table('filter_scratch')->insert([
            ['name' => 'Alpha', 'price' => 100, 'status' => 'open',   'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Beta',  'price' => 200, 'status' => 'closed', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gamma', 'price' => 300, 'status' => 'open',   'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('filter_scratch');
        parent::tearDown();
    }

    private function makeModel(): Builder
    {
        return (new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'filter_scratch';
            public $timestamps = false;
        })->newQuery();
    }

    /**
     * A filter carrying the two casts, so the coercion rules are exercised the
     * way a real CMS filter exercises them.
     *
     * @param  array<string, mixed>  $params
     */
    private function castingFilter(array $params): BaseFilter
    {
        return new class ($params) extends BaseFilter {
            protected array $safeParms = [
                'is_active' => ['eq', 'in'],
                'price'     => ['eq', 'gte', 'lte', 'in'],
                'name'      => ['eq', 'like', 'in'],
                'status'    => ['eq', 'in'],
            ];

            protected array $casts = [
                'is_active' => 'bool',
                'price'     => 'int',
            ];

            protected array $searchable = ['name'];
        };
    }

    private function insert(string $name, int $price = 999, string $status = 'open'): void
    {
        DB::table('filter_scratch')->insert([
            'name' => $name, 'price' => $price, 'status' => $status,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ── Operators ─────────────────────────────────────────────────────────

    public function test_eq(): void
    {
        $q = $this->makeModel();
        (new BaseFilter(['status' => ['eq' => 'open']], ['status']))->apply($q);
        $this->assertCount(2, $q->get());
    }

    public function test_like(): void
    {
        $q = $this->makeModel();
        (new BaseFilter(['name' => ['like' => 'lph']], ['name']))->apply($q);
        $this->assertCount(1, $q->get());
    }

    public function test_gte(): void
    {
        $q = $this->makeModel();
        (new BaseFilter(['price' => ['gte' => 200]], ['price']))->apply($q);
        $this->assertCount(2, $q->get());
    }

    public function test_lte(): void
    {
        $q = $this->makeModel();
        (new BaseFilter(['price' => ['lte' => 200]], ['price']))->apply($q);
        $this->assertCount(2, $q->get());
    }

    public function test_in(): void
    {
        $q = $this->makeModel();
        (new BaseFilter(['status' => ['in' => 'open,closed']], ['status']))->apply($q);
        $this->assertCount(3, $q->get());
    }

    public function test_non_whitelisted_field_ignored(): void
    {
        $q = $this->makeModel();
        (new BaseFilter(['secret' => ['eq' => 'hack']], ['status']))->apply($q);
        $this->assertCount(3, $q->get());
    }

    // ── Rule 2: an empty value is "no filter", never `= false` ────────────

    public function test_empty_bool_param_applies_no_filter(): void
    {
        // The regression: `?is_active=` used to cast to `false` and show the
        // editor nothing but drafts.
        $q = $this->makeModel();
        $this->castingFilter(['is_active' => ''])->apply($q);
        $this->assertCount(3, $q->get());
    }

    public function test_empty_value_in_the_operator_form_applies_no_filter(): void
    {
        $q = $this->makeModel();
        $this->castingFilter(['is_active' => ['eq' => '']])->apply($q);
        $this->assertCount(3, $q->get());
    }

    public function test_empty_int_param_applies_no_filter(): void
    {
        // `(int) '' === 0` would have become `price >= 0` — every row, but by
        // accident rather than by contract.
        $q = $this->makeModel();
        $this->castingFilter(['price' => ['gte' => '']])->apply($q);
        $this->assertCount(3, $q->get());
    }

    public function test_empty_in_list_applies_no_filter_rather_than_matching_nothing(): void
    {
        $q = $this->makeModel();
        $this->castingFilter(['status' => ['in' => '']])->apply($q);
        $this->assertCount(3, $q->get());
    }

    // ── Rule 3: an uninterpretable value is rejected, never guessed ───────

    public function test_unparseable_bool_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->castingFilter(['is_active' => 'trve'])->apply($this->makeModel());
    }

    public function test_unparseable_bool_reports_the_param_that_was_fumbled(): void
    {
        try {
            $this->castingFilter(['is_active' => 'maybe'])->apply($this->makeModel());
            $this->fail('Expected a ValidationException for is_active=maybe.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_active', $e->errors());
        }
    }

    public function test_unparseable_bool_in_the_operator_form_reports_the_dotted_path(): void
    {
        try {
            $this->castingFilter(['is_active' => ['eq' => 'null']])->apply($this->makeModel());
            $this->fail('Expected a ValidationException for is_active[eq]=null.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_active.eq', $e->errors());
        }
    }

    public function test_bool_accepts_the_documented_tokens(): void
    {
        foreach (['true', 'TRUE', '1', 'yes', 'on'] as $token) {
            $q = $this->makeModel();
            $this->castingFilter(['is_active' => $token])->apply($q);
            $this->assertCount(2, $q->get(), "is_active={$token}");
        }

        foreach (['false', 'False', '0', 'no', 'off'] as $token) {
            $q = $this->makeModel();
            $this->castingFilter(['is_active' => $token])->apply($q);
            $this->assertCount(1, $q->get(), "is_active={$token}");
        }
    }

    public function test_unparseable_int_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->castingFilter(['price' => ['gte' => 'abc']])->apply($this->makeModel());
    }

    public function test_unparseable_int_inside_an_in_list_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->castingFilter(['price' => ['in' => '100,abc']])->apply($this->makeModel());
    }

    // ── Array input must not become the literal string "Array" ────────────

    public function test_array_value_for_a_scalar_operator_is_rejected(): void
    {
        // `?name[like][]=x` used to search for the string "Array".
        $this->expectException(ValidationException::class);
        $this->castingFilter(['name' => ['like' => ['x']]])->apply($this->makeModel());
    }

    public function test_array_value_for_eq_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->castingFilter(['name' => ['eq' => ['Alpha']]])->apply($this->makeModel());
    }

    // ── Repeated array params are the `in` operator ───────────────────────

    public function test_repeated_array_params_are_treated_as_in(): void
    {
        // `?status[]=open&status[]=closed` — advertised by the class docblock,
        // silently discarded before the fix.
        $q = $this->makeModel();
        $this->castingFilter(['status' => ['open', 'closed']])->apply($q);
        $this->assertCount(3, $q->get());
    }

    public function test_repeated_array_params_actually_narrow_the_result(): void
    {
        $q = $this->makeModel();
        $this->castingFilter(['status' => ['closed']])->apply($q);
        $this->assertCount(1, $q->get());
    }

    public function test_repeated_array_params_are_cast_like_any_other_value(): void
    {
        $q = $this->makeModel();
        $this->castingFilter(['is_active' => ['false']])->apply($q);
        $this->assertCount(1, $q->get());
    }

    // ── LIKE wildcards in user input are literals, not operators ──────────

    public function test_percent_in_a_like_value_is_matched_literally(): void
    {
        $this->insert('100% Cotton');

        // Before the fix the pattern became `%%%`, matching every row.
        $q = $this->makeModel();
        $this->castingFilter(['name' => ['like' => '%']])->apply($q);

        $rows = $q->get();
        $this->assertCount(1, $rows);
        $this->assertSame('100% Cotton', $rows->first()->name);
    }

    public function test_underscore_in_a_like_value_is_matched_literally(): void
    {
        $this->insert('A_B');
        $this->insert('AXB');

        $q = $this->makeModel();
        $this->castingFilter(['name' => ['like' => 'a_b']])->apply($q);

        $rows = $q->get();
        $this->assertCount(1, $rows);
        $this->assertSame('A_B', $rows->first()->name);
    }

    public function test_the_escape_character_itself_is_matched_literally(): void
    {
        $this->insert('50!% off');
        $this->insert('50 percent off');

        $q = $this->makeModel();
        $this->castingFilter(['name' => ['like' => '!%']])->apply($q);

        $rows = $q->get();
        $this->assertCount(1, $rows);
        $this->assertSame('50!% off', $rows->first()->name);
    }

    public function test_percent_in_the_search_box_is_matched_literally(): void
    {
        $this->insert('100% Cotton');

        $q = $this->makeModel();
        $this->castingFilter(['search' => '%'])->apply($q);

        $this->assertCount(1, $q->get());
    }

    // ── config('cms.locales') is the only source of the locale set ────────

    public function test_missing_locale_config_fails_loudly_instead_of_guessing(): void
    {
        Config::set('cms.locales', []);

        $filter = new class (['search' => 'x']) extends BaseFilter {
            protected array $translatable = ['name'];
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/cms\.locales/');
        $filter->apply($this->makeModel());
    }

    public function test_a_poisoned_locale_config_fails_loudly_instead_of_narrowing(): void
    {
        // `*` is not a usable locale code. The old filter dropped it and carried
        // on with `['en']`, so search silently covered a *different* locale set
        // than validation — which now rejects the whole list. Both consumers
        // must agree, and both must say so out loud.
        Config::set('cms.locales', ['en', '*']);

        $filter = new class (['search' => 'x']) extends BaseFilter {
            protected array $translatable = ['name'];
        };

        $this->expectException(RuntimeException::class);
        $filter->apply($this->makeModel());
    }

    public function test_locales_come_from_the_shared_accessor_not_a_local_read(): void
    {
        // Pinned so nobody reintroduces `config('cms.locales')` here: the
        // accessor rejects a malformed list wholesale, a direct config read
        // would happily hand back the raw array.
        Config::set('cms.locales', ['en', 'pt.BR']);

        $filter = new class (['search' => 'x']) extends BaseFilter {
            protected array $translatable = ['name'];
        };

        $this->expectException(RuntimeException::class);
        $filter->apply($this->makeModel());
    }

    public function test_translatable_search_covers_every_configured_locale(): void
    {
        Config::set('cms.locales', ['en', 'ar', 'fr', 'tr', 'es']);

        $filter = new class (['search' => 'x']) extends BaseFilter {
            protected array $translatable = ['name'];
        };

        $q = $this->makeModel();
        $filter->apply($q);

        // One OR-ed LIKE per locale — a hardcoded ['en','ar'] fallback would
        // only produce two.
        $this->assertSame(5, substr_count($q->toSql(), 'like ?'));
    }
}
