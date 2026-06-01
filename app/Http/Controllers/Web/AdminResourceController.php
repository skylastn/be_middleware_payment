<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Enums\ProjectSlug;
use App\Http\Controllers\Controller;
use App\Http\Helper\RequestHelper;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentCategory;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Model\Entity\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminResourceController extends Controller
{
    public function index(string $resource): View
    {
        $definition = $this->definition($resource);

        return view('admin.resources.index', [
            'resource' => $resource,
            'definition' => $definition,
            'records' => $definition['model']::query()
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(string $resource): View
    {
        $definition = $this->definition($resource);
        $this->ensureWritable($definition);

        return view('admin.resources.form', [
            'resource' => $resource,
            'definition' => $definition,
            'record' => null,
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $definition = $this->definition($resource);
        $this->ensureWritable($definition);

        $data = $this->payload($request, $definition);

        $definition['model']::create($data);

        return redirect()
            ->route('admin.resources.index', $resource)
            ->with('message', $definition['singular'].' created.');
    }

    public function show(string $resource, string $id): View
    {
        $definition = $this->definition($resource);

        return view('admin.resources.show', [
            'resource' => $resource,
            'definition' => $definition,
            'record' => $this->findRecord($definition, $id),
        ]);
    }

    public function edit(string $resource, string $id): View
    {
        $definition = $this->definition($resource);
        $this->ensureWritable($definition);

        return view('admin.resources.form', [
            'resource' => $resource,
            'definition' => $definition,
            'record' => $this->findRecord($definition, $id),
        ]);
    }

    public function update(Request $request, string $resource, string $id): RedirectResponse
    {
        $definition = $this->definition($resource);
        $this->ensureWritable($definition);

        $record = $this->findRecord($definition, $id);
        $record->update($this->payload($request, $definition));

        return redirect()
            ->route('admin.resources.index', $resource)
            ->with('message', $definition['singular'].' updated.');
    }

    public function destroy(string $resource, string $id): RedirectResponse
    {
        $definition = $this->definition($resource);
        $this->ensureWritable($definition);

        $record = $this->findRecord($definition, $id);
        $record->delete();

        return redirect()
            ->route('admin.resources.index', $resource)
            ->with('message', $definition['singular'].' deleted.');
    }

    public function resendOrderCallback(string $id): RedirectResponse
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($id);
        $status = $order->getStatus();

        if (! $status?->isSuccess()) {
            return back()->withErrors(['callback' => 'Only successful orders can resend callback.']);
        }

        $project = $order->project ?: Project::query()->where('type', $order->type)->first();
        if (! $project || ! $project->value || ! $project->callback) {
            return back()->withErrors(['callback' => 'Project callback configuration is incomplete.']);
        }

        $referenceParts = explode('-', (string) $order->reference);
        array_shift($referenceParts);

        RequestHelper::sendCallback(
            $project->value,
            [
                'merchantOrderId' => implode('-', $referenceParts),
                'paymentCode' => $order->payment_method,
                'resultCode' => '00',
            ],
            $project->callback,
        );

        return back()->with('message', 'Callback resent for '.$order->reference.'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $resource): array
    {
        $definitions = $this->definitions();
        abort_unless(isset($definitions[$resource]), 404);

        return $definitions[$resource];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            'orders' => [
                'title' => 'Orders',
                'singular' => 'Order',
                'model' => Order::class,
                'readonly' => true,
                'columns' => ['reference', 'type', 'payment_method', 'status', 'mode', 'created_at'],
                'fields' => [
                    'id' => ['label' => 'Order ID', 'type' => 'text', 'required' => true],
                    'reference' => ['type' => 'text', 'required' => true],
                    'type' => ['type' => 'text', 'required' => true],
                    'payment_repository_id' => ['label' => 'Payment Repository ID', 'type' => 'text'],
                    'payment_method' => ['type' => 'text'],
                    'status' => ['type' => 'select', 'options' => OrderStatus::values(), 'default' => OrderStatus::PENDING->value],
                    'mode' => ['type' => 'select', 'options' => PaymentModeType::values(), 'default' => PaymentModeType::sandbox->value],
                    'email' => ['type' => 'text'],
                    'phone' => ['type' => 'text'],
                    'address' => ['type' => 'textarea'],
                    'url' => ['type' => 'text'],
                    'notes' => ['type' => 'textarea'],
                ],
            ],
            'projects' => [
                'title' => 'Projects',
                'singular' => 'Project',
                'model' => Project::class,
                'columns' => ['name', 'type', 'slug', 'callback', 'created_at'],
                'fields' => [
                    'name' => ['type' => 'text', 'required' => true],
                    'type' => ['type' => 'text', 'required' => true],
                    'slug' => ['type' => 'select', 'options' => array_map(fn (ProjectSlug $slug): string => $slug->value, ProjectSlug::cases()), 'required' => true],
                    'key' => ['type' => 'text'],
                    'secure' => ['type' => 'text'],
                    'value' => ['type' => 'textarea'],
                    'callback' => ['type' => 'textarea', 'required' => true],
                ],
            ],
            'payment-gateways' => [
                'title' => 'Payment Gateways',
                'singular' => 'Payment Gateway',
                'model' => PaymentGateway::class,
                'columns' => ['key', 'name', 'description', 'created_at'],
                'fields' => [
                    'key' => ['type' => 'text', 'required' => true],
                    'name' => ['type' => 'text', 'required' => true],
                    'description' => ['type' => 'textarea', 'required' => true],
                ],
            ],
            'payment-repositories' => [
                'title' => 'Payment Repositories',
                'singular' => 'Payment Repository',
                'model' => PaymentRepository::class,
                'columns' => ['key', 'payment_gateway_id', 'mode', 'value', 'created_at'],
                'fields' => [
                    'payment_gateway_id' => ['label' => 'Payment Gateway ID', 'type' => 'text', 'required' => true],
                    'key' => ['type' => 'text'],
                    'mode' => ['type' => 'select', 'options' => PaymentModeType::values(), 'default' => PaymentModeType::sandbox->value],
                    'value' => ['type' => 'json', 'required' => true],
                ],
            ],
            'payment-methods' => [
                'title' => 'Payment Methods',
                'singular' => 'Payment Method',
                'model' => PaymentMethod::class,
                'columns' => ['key', 'name', 'type', 'from', 'bankCode', 'value'],
                'fields' => [
                    'key' => ['type' => 'text', 'required' => true],
                    'name' => ['type' => 'text', 'required' => true],
                    'type' => ['type' => 'text', 'required' => true],
                    'from' => ['type' => 'text', 'required' => true],
                    'bankCode' => ['label' => 'Bank Code', 'type' => 'text'],
                    'value' => ['type' => 'text'],
                ],
            ],
            'payment-categories' => [
                'title' => 'Payment Categories',
                'singular' => 'Payment Category',
                'model' => PaymentCategory::class,
                'columns' => ['key', 'title', 'detail', 'created_at'],
                'fields' => [
                    'key' => ['type' => 'text', 'required' => true],
                    'title' => ['type' => 'text', 'required' => true],
                    'detail' => ['type' => 'textarea', 'required' => true],
                ],
            ],
            'settings' => [
                'title' => 'Settings',
                'singular' => 'Setting',
                'model' => Setting::class,
                'columns' => ['key', 'value', 'updated_at'],
                'fields' => [
                    'key' => ['type' => 'text', 'required' => true],
                    'value' => ['type' => 'textarea', 'required' => true],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function payload(Request $request, array $definition): array
    {
        $rules = [];
        foreach ($definition['fields'] as $name => $field) {
            $rules[$name] = ($field['required'] ?? false) ? ['required'] : ['nullable'];
        }

        $validated = $request->validate($rules);
        foreach ($definition['fields'] as $name => $field) {
            if (! array_key_exists($name, $validated)) {
                $validated[$name] = null;
            }

            if (($field['type'] ?? null) === 'json') {
                $validated[$name] = $this->jsonValue($validated[$name]);
            }

            if ($validated[$name] === null && isset($field['default'])) {
                $validated[$name] = $field['default'];
            }
        }

        if ($definition['model'] === Project::class) {
            $validated['key'] = $validated['key'] ?: Str::random(10);
            $validated['secure'] = $validated['secure'] ?: Str::random(20);
            $validated['value'] = $validated['value'] ?: Str::random(60);
        }

        return $validated;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function findRecord(array $definition, string $id): Model
    {
        return $definition['model']::query()->findOrFail($id);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function ensureWritable(array $definition): void
    {
        abort_if($definition['readonly'] ?? false, 403, $definition['singular'].' is read-only.');
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonValue(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            back()->withErrors(['value' => 'Value must be valid JSON.'])->throwResponse();
        }

        return is_array($decoded) ? $decoded : [];
    }
}
