@extends('layouts.dashboard', ['title' => $definition['title']])

@section('content')
    <div class="page-title">
        <div>
            <div class="eyebrow">Admin</div>
            <h1>{{ $definition['title'] }}</h1>
            <div class="subtitle">
                {{ ($definition['readonly'] ?? false) ? 'View records and run available operational actions.' : 'View, create, update, and delete '.strtolower($definition['title']).'.' }}
            </div>
        </div>
        @unless ($definition['readonly'] ?? false)
            <div class="toolbar">
                <a class="button primary" href="{{ route('admin.resources.create', $resource) }}">Create {{ $definition['singular'] }}</a>
            </div>
        @endunless
    </div>

    <div class="panel">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        @foreach ($definition['columns'] as $column)
                            <th>{{ str_replace('_', ' ', $column) }}</th>
                        @endforeach
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @php
                            $recordKey = $record->getAttribute($record->getKeyName());
                            $isSuccessfulOrder = false;
                            if ($resource === 'orders') {
                                $orderStatus = $record->status instanceof \App\Enums\OrderStatus
                                    ? $record->status
                                    : \App\Enums\OrderStatus::fromName($record->status);
                                $isSuccessfulOrder = (bool) $orderStatus?->isSuccess();
                            }
                        @endphp
                        <tr>
                            @foreach ($definition['columns'] as $column)
                                @php
                                    $value = $record->{$column};
                                    if ($value instanceof \BackedEnum) {
                                        $value = $value->value;
                                    }
                                    if (is_array($value)) {
                                        $value = json_encode($value, JSON_UNESCAPED_SLASHES);
                                    }
                                @endphp
                                <td class="{{ in_array($column, ['id', 'value', 'callback', 'description'], true) ? 'mono' : '' }}">{{ $value ?: '-' }}</td>
                            @endforeach
                            <td>
                                <div class="actions">
                                    @if ($resource === 'orders')
                                        <a class="button" href="{{ route('admin.resources.show', [$resource, $recordKey]) }}">View</a>
                                    @endif

                                    @if ($resource === 'orders' && $isSuccessfulOrder)
                                        <form method="POST" action="{{ route('admin.orders.resend-callback', $recordKey) }}" onsubmit="return confirm('Resend callback for this order?')">
                                            @csrf
                                            <button class="button" type="submit">Resend Callback</button>
                                        </form>
                                    @endif

                                    @unless ($definition['readonly'] ?? false)
                                        <a class="button" href="{{ route('admin.resources.edit', [$resource, $recordKey]) }}">Edit</a>
                                        <form method="POST" action="{{ route('admin.resources.destroy', [$resource, $recordKey]) }}" onsubmit="return confirm('Delete this record?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button danger" type="submit">Delete</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($definition['columns']) + 1 }}" class="empty">No records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pagination">{{ $records->links() }}</div>
@endsection
