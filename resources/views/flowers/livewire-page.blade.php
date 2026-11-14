@extends('layouts.app', [
    'title' => $title,
    'module' => 'flowers',
    'moduleName' => 'شركة الورد المميز',
])

@section('content')
    @livewire($component, $parameters ?? [])
@endsection
