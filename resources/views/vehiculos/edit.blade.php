@extends('layouts.app')

@section('titulo', 'Editar vehículo')

@section('contenido')
    <h1 class="mb-4">Editar {{ $vehiculo->marca }} {{ $vehiculo->modelo }}</h1>

    {{-- multipart/form-data: hace falta para poder mandar archivos (fotos) --}}
    <form action="{{ route('vehiculos.update', $vehiculo) }}" method="POST" enctype="multipart/form-data">
        @csrf
        {{-- La ruta update espera PUT; el formulario lo simula con este campo oculto --}}
        @method('PUT')
        @include('vehiculos._form')
        <button type="submit" class="btn btn-success">Guardar cambios</button>
        <a href="{{ route('vehiculos.show', $vehiculo) }}" class="btn btn-secondary">Cancelar</a>
    </form>
@endsection
