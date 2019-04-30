const url = new URL("{{ rtrim(config('app.docs_url') ?: config('app.url'), '/') }}/{{ ltrim($route['uri'], '/') }}");
@if(count($route['queryParameters']))

let params = {
@foreach($route['queryParameters'] as $attribute => $parameter)
    "{{ $attribute }}": "{{ $parameter['value'] }}",
@endforeach
};

Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
@endif

let headers = {
@foreach($route['headers'] as $header => $value)
    "{{$header}}": "{{$value}}",
@endforeach
@if(!array_key_exists('Accept', $route['headers']))
    "Accept": "application/json",
@endif
@if(!array_key_exists('Content-Type', $route['headers']))
    "Content-Type": "application/json",
@endif
}
@if(count($route['bodyParameters']))

let body = JSON.stringify({
@foreach($route['bodyParameters'] as $attribute => $parameter)
    "{{ $attribute }}": @if (in_array($parameter['type'], ['json', 'object'])){!! $parameter['value'] !!}@else"{{ $parameter['value'] }}"@endif,
@endforeach
})
@endif

fetch(url, {
    method: "{{$route['methods'][0]}}",
    headers: headers,
@if(count($route['bodyParameters']))
    body: body
@endif
})
    .then(response => response.json())
    .then(json => console.log(json));
