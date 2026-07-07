<?php

namespace Tests\Unit;

use App\Base\BaseFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            $t->timestamps();
        });
        DB::table('filter_scratch')->insert([
            ['name' => 'Alpha', 'price' => 100, 'status' => 'open',   'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Beta',  'price' => 200, 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gamma', 'price' => 300, 'status' => 'open',   'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('filter_scratch');
        parent::tearDown();
    }

    private function makeModel(): \Illuminate\Database\Eloquent\Builder
    {
        return (new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'filter_scratch';
            public $timestamps = false;
        })->newQuery();
    }

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
}
