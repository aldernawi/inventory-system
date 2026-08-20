@extends('layouts.app', [
    'title' => $title,
    'module' => 'flowers',
    'moduleName' => 'إدارة مخزون الورد',
])

@section('content')
    @livewire($component, $parameters ?? [])
@endsection
