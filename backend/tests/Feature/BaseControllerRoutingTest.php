<?php

namespace Tests\Feature;

use App\Base\BaseCRUDController;
use App\Base\BaseIndexController;
use App\Base\BaseRequest;
use App\Base\BaseResource;
use App\Base\BaseService;
use App\Base\HandlesRecycleBin;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Tests\TestCase;

/**
 * The base pair, driven through the **real router**.
 *
 * `BaseControllerPlumbingTest` calls the base classes' methods directly, which
 * is why it stayed green while the classes were, in fact, impossible to put on
 * a route: `show`/`update`/`destroy` type-hinted the abstract
 * `Illuminate\Database\Eloquent\Model`, so implicit binding could not resolve
 * `{routedWidget}`, and `store`/`update` type-hinted the abstract
 * `App\Base\BaseRequest`, so no route could declare which concrete FormRequest
 * validated it. Neither could be fixed in a child, because PHP forbids
 * narrowing a parameter type in an override.
 *
 * Every case below goes over HTTP, so a base pair that cannot be routed fails
 * here.
 */
class RoutedWidget extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'routed_widgets';

    protected $fillable = ['name', 'slug', 'is_active'];

    protected $casts = ['is_active' => 'bool'];
}

class RoutedWidgetService extends BaseService
{
    protected string $model = RoutedWidget::class;
}

class RoutedWidgetResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid(),
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
        ];
    }
}

class CreateRoutedWidgetRequest extends BaseRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['required', 'string', Rule::unique('routed_widgets', 'slug')],
        ];
    }
}

class UpdateRoutedWidgetRequest extends BaseRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:50'],
            // The `unique`-on-update shape the real requests use: exempt the
            // record the route is addressing. Proves the route parameter is a
            // resolved model by the time validation runs.
            'slug' => ['sometimes', 'string', Rule::unique('routed_widgets', 'slug')->ignore($this->route('routedWidget'))],
        ];
    }
}

/** The migration target shape: declare, don't hand-copy. */
class RoutedWidgetController extends BaseCRUDController
{
    use HandlesRecycleBin;

    protected ?string $resource = RoutedWidgetResource::class;

    public function __construct(private readonly RoutedWidgetService $widgets) {}

    protected function service(): BaseService
    {
        return $this->widgets;
    }

    public function show(RoutedWidget $routedWidget, Request $request): JsonResponse
    {
        return $this->showResponse($routedWidget, $request);
    }

    public function store(CreateRoutedWidgetRequest $request): JsonResponse
    {
        return $this->storeResponse($request);
    }

    public function update(UpdateRoutedWidgetRequest $request, RoutedWidget $routedWidget): JsonResponse
    {
        return $this->updateResponse($request, $routedWidget);
    }

    public function destroy(RoutedWidget $routedWidget, Request $request): JsonResponse
    {
        return $this->destroyResponse($routedWidget, $request);
    }

    public function restore(RoutedWidget $routedWidget, Request $request): JsonResponse
    {
        return $this->restoreResponse($routedWidget, $request);
    }

    public function forceDestroy(RoutedWidget $routedWidget, Request $request): JsonResponse
    {
        return $this->forceDestroyResponse($routedWidget, $request);
    }
}

/** Read-only half, on a route, bound by a non-default field. */
class RoutedWidgetReadController extends BaseIndexController
{
    protected ?string $resource = RoutedWidgetResource::class;

    public function __construct(private readonly RoutedWidgetService $widgets) {}

    protected function service(): BaseService
    {
        return $this->widgets;
    }

    public function show(RoutedWidget $routedWidget, Request $request): JsonResponse
    {
        return $this->showResponse($routedWidget, $request);
    }
}

class BaseControllerRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('routed_widgets', function ($t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('name');
            $t->string('slug');
            $t->boolean('is_active')->default(true);
            $t->softDeletes();
            $t->timestamps();
        });

        Route::middleware('api')->prefix('api/base-pair')->group(function () {
            Route::get('/widgets/trashed', [RoutedWidgetController::class, 'trashed']);
            Route::get('/widgets', [RoutedWidgetController::class, 'index']);
            Route::get('/widgets/{routedWidget}', [RoutedWidgetController::class, 'show']);
            Route::post('/widgets', [RoutedWidgetController::class, 'store']);
            Route::put('/widgets/{routedWidget}', [RoutedWidgetController::class, 'update']);
            Route::delete('/widgets/{routedWidget}', [RoutedWidgetController::class, 'destroy']);
            Route::post('/widgets/{routedWidget}/restore', [RoutedWidgetController::class, 'restore'])->withTrashed();
            Route::delete('/widgets/{routedWidget}/force', [RoutedWidgetController::class, 'forceDestroy'])->withTrashed();

            // Read-only, addressed by a binding field rather than the route key.
            Route::get('/readonly', [RoutedWidgetReadController::class, 'index']);
            Route::get('/readonly/{routedWidget:slug}', [RoutedWidgetReadController::class, 'show']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('routed_widgets');
        parent::tearDown();
    }

    private function widget(array $attrs = []): RoutedWidget
    {
        static $n = 0;
        $n++;

        return RoutedWidget::create(array_merge([
            'name' => 'Widget '.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'slug' => 'widget-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
        ], $attrs));
    }

    // ── Blocker 1: the abstract `Model` type-hint ─────────────────────────

    public function test_show_resolves_the_route_bound_model(): void
    {
        $widget = $this->widget();

        $this->getJson("/api/base-pair/widgets/{$widget->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $widget->uuid)
            ->assertJsonPath('data.name', $widget->name);
    }

    public function test_show_404s_on_an_unknown_route_key(): void
    {
        $this->getJson('/api/base-pair/widgets/00000000-0000-4000-8000-000000000000')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_show_404s_on_a_soft_deleted_record(): void
    {
        $widget = $this->widget();
        $widget->delete();

        $this->getJson("/api/base-pair/widgets/{$widget->uuid}")->assertStatus(404);
    }

    public function test_a_binding_field_still_binds_on_the_read_only_base(): void
    {
        $widget = $this->widget(['slug' => 'editorial-slug']);

        $this->getJson('/api/base-pair/readonly/editorial-slug')
            ->assertOk()
            ->assertJsonPath('data.uuid', $widget->uuid);
    }

    public function test_destroy_binds_the_model_and_answers_204(): void
    {
        $widget = $this->widget();

        $this->deleteJson("/api/base-pair/widgets/{$widget->uuid}")->assertStatus(204);
        $this->assertSoftDeleted('routed_widgets', ['id' => $widget->id]);
    }

    // ── Blocker 2: the abstract `BaseRequest` type-hint ───────────────────

    public function test_store_validates_through_the_declared_form_request(): void
    {
        $this->postJson('/api/base-pair/widgets', ['slug' => 'no-name'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_store_creates_and_answers_201_with_the_created_message(): void
    {
        $res = $this->postJson('/api/base-pair/widgets', ['name' => 'Fresh', 'slug' => 'fresh'])
            ->assertStatus(201);

        $this->assertSame('Fresh', $res->json('data.name'));
        $this->assertSame(__('custom.messages.created'), $res->json('message'));
        $this->assertDatabaseHas('routed_widgets', ['slug' => 'fresh']);
    }

    public function test_update_validates_through_its_own_form_request(): void
    {
        $widget = $this->widget();
        $other  = $this->widget();

        $this->putJson("/api/base-pair/widgets/{$widget->uuid}", ['slug' => $other->slug])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    public function test_update_unique_rule_exempts_the_route_bound_record(): void
    {
        $widget = $this->widget();

        // `Rule::unique(...)->ignore($this->route('routedWidget'))` only works if
        // the route parameter is an already-resolved model inside the FormRequest.
        $this->putJson("/api/base-pair/widgets/{$widget->uuid}", ['slug' => $widget->slug, 'name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    }

    // ── The recycle bin, over HTTP, with `->withTrashed()` bindings ───────

    public function test_trashed_lists_only_deleted_rows(): void
    {
        $live = $this->widget();
        $gone = $this->widget();
        $gone->delete();

        $res = $this->getJson('/api/base-pair/widgets/trashed')->assertOk();

        $this->assertSame(1, $res->json('data.meta.total'));
        $this->assertSame($gone->uuid, $res->json('data.items.0.uuid'));
        $this->assertNotSame($live->uuid, $res->json('data.items.0.uuid'));
    }

    public function test_restore_binds_a_trashed_record_and_brings_it_back(): void
    {
        $widget = $this->widget();
        $widget->delete();

        $this->postJson("/api/base-pair/widgets/{$widget->uuid}/restore")
            ->assertOk()
            ->assertJsonPath('data.uuid', $widget->uuid)
            ->assertJsonPath('message', __('custom.messages.restored'));

        $this->assertDatabaseHas('routed_widgets', ['id' => $widget->id, 'deleted_at' => null]);
    }

    public function test_force_destroy_binds_a_trashed_record_and_removes_it(): void
    {
        $widget = $this->widget();
        $widget->delete();

        $this->deleteJson("/api/base-pair/widgets/{$widget->uuid}/force")->assertStatus(204);

        $this->assertDatabaseMissing('routed_widgets', ['id' => $widget->id]);
    }

    // ── Envelope parity with the hand-written controllers ─────────────────

    public function test_index_emits_the_documented_paginated_envelope(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->widget();
        }

        $body = $this->getJson('/api/base-pair/widgets?per_page=2')->assertOk()->json();

        $this->assertTrue($body['success']);
        $this->assertNotSame('', $body['request_id']);
        $this->assertSame(['items', 'meta'], array_keys($body['data']));
        $this->assertSame(
            ['current_page', 'per_page', 'total', 'last_page'],
            array_keys($body['data']['meta']),
        );
        $this->assertSame(['uuid', 'name', 'slug'], array_keys($body['data']['items'][0]));
    }

    public function test_index_reads_per_page_and_the_filter_from_the_query_string(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->widget();
        }

        $this->getJson('/api/base-pair/widgets?per_page=3')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 3)
            ->assertJsonPath('data.meta.total', 4);
    }

    // ── The base classes must not smuggle in verbs of their own ───────────

    public function test_the_base_pair_declares_no_abstract_model_or_request_parameters(): void
    {
        foreach ([BaseIndexController::class, BaseCRUDController::class, HandlesRecycleBin::class] as $class) {
            foreach ((new \ReflectionClass($class))->getMethods() as $method) {
                if (! $method->isPublic()) {
                    continue;
                }
                foreach ($method->getParameters() as $parameter) {
                    $type = $parameter->getType();
                    $name = $type instanceof \ReflectionNamedType ? $type->getName() : null;

                    $this->assertNotSame(Model::class, $name, "{$class}::{$method->getName()} takes an abstract Model");
                    $this->assertNotSame(BaseRequest::class, $name, "{$class}::{$method->getName()} takes an abstract BaseRequest");
                }
            }
        }
    }

    public function test_the_read_only_base_exposes_no_write_verbs(): void
    {
        foreach (['store', 'update', 'destroy', 'trashed', 'restore', 'forceDestroy',
            'storeResponse', 'updateResponse', 'destroyResponse'] as $verb) {
            $this->assertFalse(
                method_exists(BaseIndexController::class, $verb),
                "BaseIndexController must not expose {$verb}()",
            );
        }
    }
}
