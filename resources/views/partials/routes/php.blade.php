
$headers = [
@foreach($route['headers'] as $header => $value)
    "{{$header}}" => "{{$value}}",
@endforeach
@if(!array_key_exists('Accept', $route['headers']))
    "Accept" => "application/json",
@endif
@if(!array_key_exists('Content-Type', $route['headers']))
    "Content-Type" => "application/json",
@endif
];
@if(count($route['bodyParameters']))

$body = [
@foreach($route['bodyParameters'] as $attribute => $parameter)
    "{{ $attribute }}" => @if (in_array($parameter['type'], ['json', 'object', 'array']))"json_decode({!! $parameter['value']!!}, true)"@else "{{ $parameter['value'] }}"@endif,
@endforeach
];
@endif
@if(count($route['queryParameters']))

$query = [
@foreach($route['queryParameters'] as $attribute => $parameter)
    "{{ $attribute }}" => "{{ $parameter['value'] }}",
@endforeach
];
@endif

@php
    $urlVlaue = rtrim(config('app.docs_url') ?: config('app.url'), '/') . '/' . ltrim($route['uri'], '/');
    $urlParams = '';
    if(count($route['queryParameters'])) {
        $urlVlaue .= '?';
        $urlParams = 'http_build_query($data)';
    }
@endphp

$ch = curl_init("{{$urlVlaue}}"{{$urlParams ? ' . ' . $urlParams : '' }});
@unless($route['methods'][0] === 'GET')
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "{{$route['methods'][0]}}");
@endunless
@if($route['methods'][0] === 'POST' && count($route['bodyParameters']))
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
@endif
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
  throw \Exception($err);
} else {
  return $response;
}
