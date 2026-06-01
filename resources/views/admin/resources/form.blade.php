@extends('layouts.dashboard', ['title' => ($record ? 'Edit ' : 'Create ').$definition['singular']])

@section('content')
    <div class="page-title">
        <div>
            <div class="eyebrow">Admin</div>
            <h1>{{ $record ? 'Edit' : 'Create' }} {{ $definition['singular'] }}</h1>
            <div class="subtitle">Manage {{ strtolower($definition['singular']) }} details.</div>
        </div>
        <div class="toolbar">
            <a class="button" href="{{ route('admin.resources.index', $resource) }}">Back</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert" style="margin-bottom: 14px;">{{ $errors->first() }}</div>
    @endif

    <div class="panel">
        @php
            $recordKey = $record?->getAttribute($record?->getKeyName());
        @endphp
        <form class="form-grid" method="POST" action="{{ $record ? route('admin.resources.update', [$resource, $recordKey]) : route('admin.resources.store', $resource) }}">
            @csrf
            @if ($record)
                @method('PUT')
            @endif

            @foreach ($definition['fields'] as $name => $field)
                @php
                    $rawValue = old($name, $record?->{$name} ?? ($field['default'] ?? ''));
                    if ($rawValue instanceof \BackedEnum) {
                        $rawValue = $rawValue->value;
                    }
                    if (is_array($rawValue)) {
                        $rawValue = json_encode($rawValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    }
                    $label = $field['label'] ?? str_replace('_', ' ', ucfirst($name));
                    $type = $field['type'] ?? 'text';
                @endphp
                <label class="field {{ in_array($type, ['textarea', 'json'], true) ? 'full' : '' }}">
                    <span class="label">{{ $label }}</span>
                    @if ($type === 'select')
                        <select class="input" name="{{ $name }}" {{ ($field['required'] ?? false) ? 'required' : '' }}>
                            @foreach ($field['options'] as $option)
                                <option value="{{ $option }}" @selected((string) $rawValue === (string) $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    @elseif (in_array($type, ['textarea', 'json'], true))
                        <textarea class="input mono" name="{{ $name }}" {{ ($field['required'] ?? false) ? 'required' : '' }}>{{ $rawValue }}</textarea>
                    @else
                        <input class="input" name="{{ $name }}" value="{{ $rawValue }}" {{ ($field['required'] ?? false) ? 'required' : '' }}>
                    @endif
                </label>
            @endforeach

            @if ($resource === 'projects' && $record)
                @foreach (['key', 'secure', 'value'] as $credentialField)
                    @php
                        $credentialValue = $record->{$credentialField};
                        $credentialLabel = str_replace('_', ' ', ucfirst($credentialField));
                    @endphp
                    <label class="field {{ $credentialField === 'value' ? 'full' : '' }}">
                        <span class="label">{{ $credentialLabel }}</span>
                        @if ($credentialField === 'value')
                            <textarea class="input mono" readonly>{{ $credentialValue }}</textarea>
                        @else
                            <input class="input mono" value="{{ $credentialValue }}" readonly>
                        @endif
                    </label>
                @endforeach
            @endif

            <div class="field full">
                <button class="button primary" type="submit">{{ $record ? 'Save Changes' : 'Create Record' }}</button>
            </div>
        </form>
    </div>
@endsection
