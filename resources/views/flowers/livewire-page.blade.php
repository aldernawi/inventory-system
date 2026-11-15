@extends('layouts.app', [
    'title' => $title,
    'module' => 'flowers',
    'moduleName' => 'شركة الورود المميزة',
])

@section('content')
    @livewire($component, $parameters ?? [])
@endsection
