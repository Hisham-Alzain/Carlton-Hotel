<?php

namespace Tests\Unit;

use App\Base\BaseCRUDController;
use App\Base\BaseFilter;
use App\Base\BaseIndexController;
use App\Base\BaseRequest;
use App\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Test-only fixtures. `BaseCRUDController` and `BaseIndexController` are the
 * architecture the `tupcode-laravel-backend` skill mandates, but no controller
 * in `app/` extends them yet — every one of the 25 hand-copies the index
 * one-liner instead. That left the `per_page` and filter plumbing inside the
 * base classes never executed and never tested, so a change to it could break
 * the mandated path with a green suite. These fixtures make the base classes
 * run for real.
 */
class PlumbingWidget extends Model
{
    protected $table = 'plumbing_widgets';

    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'bool'];
}

class PlumbingWidgetFilter extends BaseFilter
{
    protected array $safeParms = [
        'is_active' => ['eq'],
        'name'      => ['like'],
    ];

    protected array $casts = ['is_active' => 'bool'];

    protected array $searchable = ['name'];

    protected array $sortable = ['name'];
}

class PlumbingWidgetService extends BaseService
{
    protected string $model = PlumbingWidget::class;

    protected ?string $filter = PlumbingWidgetFilter::class;
}

class PlumbingCrudController extends BaseCRUDController
{
    public function __construct(private readonly PlumbingWidgetService $widgets) {}

    protected function service(): BaseService
    {
        return $this->widgets;
    }
}

class PlumbingReadOnlyController extends BaseIndexController
{
    public function __construct(private readonly PlumbingWidgetService $widgets) {}

    protected function service(): BaseService
    {
        return $this->widgets;
    }
}

class PlumbingWidgetResource extends \App\Base\BaseResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'name'      => $this->resource->name,
            'published' => (bool) $this->resource->is_active,
        ];
    }
}

/** The shape the skill actually prescribes: declare `$resource`, inherit the rest. */
class PlumbingResourceController extends BaseCRUDController
{
    protected ?string $resource = PlumbingWidgetResource::class;

    public function __construct(private readonly PlumbingWidgetService $widgets) {}

    protected function service(): BaseService
    {
        return $this->widgets;
    }
}

class PlumbingWidgetRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name'      => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

class BaseControllerPlumbingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('plumbing_widgets', function ($t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('plumbing_widgets');
        parent::tearDown();
    }

    private function crud(): PlumbingCrudController
    {
        return $this->app->make(PlumbingCrudController::class);
    }

    private function readOnly(): PlumbingReadOnlyController
    {
        return $this->app->make(PlumbingReadOnlyController::class);
    }

    /** @param  array<string, mixed>  $query */
    private function queryRequest(array $query = []): Request
    {
        return Request::create('/widgets', 'GET', $query);
    }

    /**
     * Build a resolved FormRequest the way the router would, so
     * `$request->validated()` has something to return.
     *
     * @param  array<string, mixed>  $data
     */
    private function validatedRequest(array $data, string $method = 'POST'): BaseRequest
    {
        $request = PlumbingWidgetRequest::create('/widgets', $method, $data);
        $request->setContainer($this->app)->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        return $request;
    }

    private function seedWidgets(int $active, int $drafts = 0): void
    {
        for ($i = 0; $i < $active; $i++) {
            PlumbingWidget::create(['name' => 'Widget ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'is_active' => true]);
        }
        for ($i = 0; $i < $drafts; $i++) {
            PlumbingWidget::create(['name' => 'Draft ' . $i, 'is_active' => false]);
        }
    }

    /** @return array<string, mixed> */
    private function payload(\Illuminate\Http\JsonResponse $response): array
    {
        return $response->getData(true);
    }

    // ── per_page plumbing (BaseCRUDController) ────────────────────────────

    public function test_crud_index_honours_a_client_supplied_per_page(): void
    {
        $this->seedWidgets(60);

        $body = $this->payload($this->crud()->index($this->queryRequest(['per_page' => 50])));

        $this->assertCount(50, $body['data']['items']);
        $this->assertSame(50, $body['data']['meta']['per_page']);
        $this->assertSame(60, $body['data']['meta']['total']);
        $this->assertSame(2, $body['data']['meta']['last_page']);
    }

    public function test_crud_index_defaults_to_the_service_page_size(): void
    {
        $this->seedWidgets(20);

        $body = $this->payload($this->crud()->index($this->queryRequest()));

        $this->assertCount(15, $body['data']['items']);
        $this->assertSame(15, $body['data']['meta']['per_page']);
    }

    public function test_crud_index_clamps_per_page_to_the_service_cap(): void
    {
        $this->seedWidgets(120);

        $body = $this->payload($this->crud()->index($this->queryRequest(['per_page' => 500])));

        $this->assertSame(100, $body['data']['meta']['per_page']);
        $this->assertCount(100, $body['data']['items']);
    }

    public function test_crud_index_falls_back_to_the_default_for_junk_per_page(): void
    {
        $this->seedWidgets(20);

        foreach (['0', '-5', 'abc'] as $value) {
            $body = $this->payload($this->crud()->index($this->queryRequest(['per_page' => $value])));
            $this->assertSame(15, $body['data']['meta']['per_page'], "per_page={$value}");
        }
    }

    // ── filter plumbing (BaseCRUDController) ──────────────────────────────

    public function test_crud_index_hands_the_query_string_to_the_filter(): void
    {
        $this->seedWidgets(5, drafts: 2);

        $body = $this->payload($this->crud()->index($this->queryRequest(['is_active' => 'false'])));

        $this->assertSame(2, $body['data']['meta']['total']);
    }

    public function test_crud_index_applies_search_through_the_filter(): void
    {
        $this->seedWidgets(4, drafts: 3);

        $body = $this->payload($this->crud()->index($this->queryRequest(['search' => 'draft'])));

        $this->assertSame(3, $body['data']['meta']['total']);
    }

    public function test_crud_index_applies_sort_through_the_filter(): void
    {
        PlumbingWidget::create(['name' => 'Charlie']);
        PlumbingWidget::create(['name' => 'Alpha']);
        PlumbingWidget::create(['name' => 'Bravo']);

        $body = $this->payload($this->crud()->index($this->queryRequest(['sort' => 'name', 'sort_dir' => 'desc'])));

        $this->assertSame(
            ['Charlie', 'Bravo', 'Alpha'],
            array_column($body['data']['items'], 'name'),
        );
    }

    public function test_crud_index_treats_an_empty_filter_value_as_no_filter(): void
    {
        $this->seedWidgets(5, drafts: 2);

        // The `?is_active=` regression, pinned on the mandated path too.
        $body = $this->payload($this->crud()->index($this->queryRequest(['is_active' => ''])));

        $this->assertSame(7, $body['data']['meta']['total']);
    }

    public function test_crud_index_rejects_an_uninterpretable_filter_value(): void
    {
        $this->seedWidgets(3);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->crud()->index($this->queryRequest(['is_active' => 'trve']));
    }

    // ── envelope shape must match what the 25 hand-copies produce ─────────

    public function test_crud_index_returns_the_documented_envelope(): void
    {
        $this->seedWidgets(3);

        $body = $this->payload($this->crud()->index($this->queryRequest()));

        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('request_id', $body);
        $this->assertSame(['items', 'meta'], array_keys($body['data']));
        $this->assertSame(
            ['current_page', 'per_page', 'total', 'last_page'],
            array_keys($body['data']['meta']),
        );
    }

    // ── the read-only base class ──────────────────────────────────────────

    public function test_index_controller_shares_the_same_per_page_and_filter_plumbing(): void
    {
        $this->seedWidgets(30, drafts: 5);

        $body = $this->payload($this->readOnly()->index($this->queryRequest(['per_page' => 25, 'is_active' => 'true'])));

        $this->assertSame(25, $body['data']['meta']['per_page']);
        $this->assertSame(30, $body['data']['meta']['total']);
    }

    public function test_index_controller_show_returns_the_model(): void
    {
        $widget = PlumbingWidget::create(['name' => 'Solo']);

        $body = $this->payload($this->readOnly()->show($widget, $this->queryRequest()));

        $this->assertSame('Solo', $body['data']['name']);
    }

    public function test_index_controller_exposes_no_write_verbs(): void
    {
        // Read-only is the whole point of the class; a write verb appearing here
        // would silently widen every endpoint that extends it.
        foreach (['store', 'update', 'destroy'] as $verb) {
            $this->assertFalse(
                method_exists(PlumbingReadOnlyController::class, $verb),
                "BaseIndexController must not expose {$verb}()",
            );
        }
    }

    // ── the write verbs on BaseCRUDController ─────────────────────────────

    public function test_crud_show_returns_the_model(): void
    {
        $widget = PlumbingWidget::create(['name' => 'Solo']);

        $body = $this->payload($this->crud()->show($widget, $this->queryRequest()));

        $this->assertTrue($body['success']);
        $this->assertSame('Solo', $body['data']['name']);
    }

    public function test_crud_store_creates_the_model_and_answers_201(): void
    {
        $response = $this->crud()->store($this->validatedRequest(['name' => 'Fresh']));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Fresh', $this->payload($response)['data']['name']);
        $this->assertDatabaseHas('plumbing_widgets', ['name' => 'Fresh']);
    }

    public function test_crud_update_persists_the_change(): void
    {
        $widget = PlumbingWidget::create(['name' => 'Before']);

        $response = $this->crud()->update($this->validatedRequest(['name' => 'After'], 'PUT'), $widget);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('After', $this->payload($response)['data']['name']);
        $this->assertDatabaseHas('plumbing_widgets', ['id' => $widget->id, 'name' => 'After']);
    }

    // ── the declared `$resource` — the shape the skill prescribes ─────────

    public function test_declared_resource_shapes_every_item_in_the_index(): void
    {
        $this->seedWidgets(2, drafts: 1);

        $body = $this->payload($this->app->make(PlumbingResourceController::class)->index($this->queryRequest()));

        $this->assertSame(3, $body['data']['meta']['total']);
        foreach ($body['data']['items'] as $item) {
            $this->assertSame(['name', 'published'], array_keys($item));
        }
    }

    public function test_declared_resource_produces_the_same_envelope_as_the_hand_written_controllers(): void
    {
        $this->seedWidgets(3);

        $body = $this->payload(
            $this->app->make(PlumbingResourceController::class)->index($this->queryRequest(['per_page' => 2]))
        );

        $this->assertSame(['items', 'meta'], array_keys($body['data']));
        $this->assertSame(
            ['current_page', 'per_page', 'total', 'last_page'],
            array_keys($body['data']['meta']),
        );
        $this->assertSame(2, $body['data']['meta']['per_page']);
        $this->assertSame(2, $body['data']['meta']['last_page']);
    }

    public function test_declared_resource_shapes_show_and_store_too(): void
    {
        $controller = $this->app->make(PlumbingResourceController::class);
        $widget     = PlumbingWidget::create(['name' => 'Shaped']);

        $shown = $this->payload($controller->show($widget, $this->queryRequest()));
        $this->assertSame(['name', 'published'], array_keys($shown['data']));

        $stored = $this->payload($controller->store($this->validatedRequest(['name' => 'Made'])));
        $this->assertSame(['name', 'published'], array_keys($stored['data']));
    }

    public function test_crud_destroy_deletes_and_answers_204(): void
    {
        $widget = PlumbingWidget::create(['name' => 'Doomed']);

        $response = $this->crud()->destroy($widget, $this->queryRequest());

        $this->assertSame(204, $response->getStatusCode());
        $this->assertDatabaseMissing('plumbing_widgets', ['id' => $widget->id]);
    }
}
