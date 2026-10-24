@extends('layouts.hub')

@section('title', 'Artist not found')

@section('content')
  <h1>{{ isset($error) ? 'Artist details unavailable' : 'No artist found' }}</h1>
  <p class="notice">{{ $error ?? "MusicBrainz has no artist called “{$name}”." }}</p>
  <p><a href="{{ route('modules.radio') }}">Back to Radio</a></p>
@endsection
