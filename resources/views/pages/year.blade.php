@extends('layouts.app')

@section('content')
    @include('components.year-content', [
        'year' => $year,
        'jam' => $jam,
        'projects' => $projects,
    ])
@endsection


