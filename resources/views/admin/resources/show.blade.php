@extends('layouts.dashboard', ['title' => 'View '.$definition['singular']])

@section('content')
    @php
        $recordKey = $record->getAttribute($record->getKeyName());
        $orderStatus = null;
        $isSuccessfulOrder = false;
        if ($resource === 'orders') {
            $orderStatus = $record->status instanceof \App\Enums\OrderStatus
                ? $record->status
                : \App\Enums\OrderStatus::fromName($record->status);
            $isSuccessfulOrder = (bool) $orderStatus?->isSuccess();
        }
    @endphp

    <div class="page-title">
        <div>
            <div class="eyebrow">Admin</div>
            <h1>View {{ $definition['singular'] }}</h1>
            <div class="subtitle">Inspect record details without changing the stored data.</div>
        </div>
        <div class="toolbar">
            <a class="button" href="{{ route('admin.resources.index', $resource) }}">Back</a>
            @if ($resource === 'orders' && $isSuccessfulOrder)
                <form method="POST" action="{{ route('admin.orders.resend-callback', $recordKey) }}" onsubmit="return confirm('Resend callback for this order?')">
                    @csrf
                    <button class="button primary" type="submit">Resend Callback</button>
                </form>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div class="alert" style="margin-bottom: 14px;">{{ $errors->first() }}</div>
    @endif

    <div class="panel">
        <div class="detail-list">
            @foreach ($record->getAttributes() as $name => $rawValue)
                @php
                    $value = $record->{$name} ?? $rawValue;
                    if ($value instanceof \BackedEnum) {
                        $value = $value->value;
                    }
                    if (is_array($value)) {
                        $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    }
                    $isLong = is_string($value) && strlen($value) > 90;
                @endphp
                <div class="detail-row {{ $isLong ? 'wide' : '' }}">
                    <div class="label">{{ str_replace('_', ' ', $name) }}</div>
                    <div class="{{ $isLong || in_array($name, ['id', 'value', 'callback', 'notes', 'url'], true) ? 'mono' : '' }}">
                        {{ ($value === null || $value === '') ? '-' : $value }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
