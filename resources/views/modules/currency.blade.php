@extends('layouts.hub')

@section('title', $module->title)

@section('content')
  <h1>{{ $module->title }}</h1>

  @isset($currencies)
    <form class="inline-form" method="get">
      <label for="amount">Amount</label>
      <input id="amount" name="amount" value="{{ $amount }}" inputmode="decimal" required>
      <label for="from">From</label>
      <select id="from" name="from">
        @foreach ($currencies as $code => $name)
          <option value="{{ $code }}" @selected($code === $source)>{{ $code }} · {{ $name }}</option>
        @endforeach
      </select>
      <label for="to">To</label>
      <select id="to" name="to">
        @foreach ($currencies as $code => $name)
          <option value="{{ $code }}" @selected($code === $target)>{{ $code }} · {{ $name }}</option>
        @endforeach
      </select>
      <button type="submit">Convert</button>
    </form>
  @endisset

  @isset($error)
    <p class="notice">{{ $error }}</p>
  @elseif (isset($conversion))
    <section class="panel">
      <p class="big">{{ number_format($conversion['result'], 2, '.', '') }} {{ $target }}</p>
      <p>{{ $amount }} {{ $source }} at 1 {{ $source }} = {{ $conversion['rate'] }} {{ $target }}@if ($conversion['date']) (rate from {{ $conversion['date'] }})@endif</p>
    </section>
  @endisset

  <p class="source">Data: Frankfurter (European Central Bank rates)</p>
@endsection
