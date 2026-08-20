@extends('layouts.app', [
    'title' => $title,
    'module' => 'salami',
    'moduleName' => 'إدارة مخزن السلامي',
])

@section('content')
    @livewire($component, $parameters ?? [])
@endsection
