@extends('templates/wrapper', [
    'css' => ['body' => 'bg-neutral-900']
])

@section('user-data')
    @parent
    @if(session()->has('auth_error'))
        <script>
            window.AuthError = {!! json_encode(session('auth_error'), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
        </script>
    @endif
@endsection

@section('container')
    <div id="app"></div>
@endsection
