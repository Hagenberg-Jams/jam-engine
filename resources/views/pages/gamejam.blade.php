@extends('hyde::layouts.app')

@section('content')
    @include('components.gamejam-content', [
        'year' => $year,
        'jam' => $jam,
        'games' => $games,
    ])
@endsection


