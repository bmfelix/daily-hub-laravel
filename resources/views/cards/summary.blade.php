<div @if ($module->liveCard) data-card-url="{{ $module->url(['card' => 1]) }}" @endif @if ($module->slug === 'weather') data-locate @endif>
  <p>{{ $module->summary }}</p>
</div>
