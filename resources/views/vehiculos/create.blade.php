@extends('layouts.app')

@section('titulo', 'Nuevo vehículo')

@section('contenido')
    <h1 class="mb-4">Nuevo vehículo</h1>

    {{-- multipart/form-data: hace falta para poder mandar archivos (fotos) --}}
    <form action="{{ route('vehiculos.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('vehiculos._form')
        <button type="submit" class="btn btn-success">Guardar</button>
        <a href="{{ route('vehiculos.index') }}" class="btn btn-secondary">Cancelar</a>
    </form>
@endsection
