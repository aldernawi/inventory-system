@extends('layouts.app', [
    'title' => $title,
    'module' => 'salami',
    'moduleName' => 'شركة النظام المتجدد',
])

@section('content')
    @livewire($component, $parameters ?? [])
@endsection
