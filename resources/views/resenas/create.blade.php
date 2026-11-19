@extends('layouts.app')

@section('titulo', 'Dejar reseña')

@push('estilos')
    <link href="{{ asset('css/consultas.css') }}?v={{ filemtime(public_path('css/consultas.css')) }}" rel="stylesheet">
@endpush

@use('App\Models\Resena')

@section('contenido')
    @php $v = $consulta->vehiculo; $portada = $v->fotos->first(); @endphp

    <a href="{{ route('consultas.mias') }}" class="enlace-volver">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a mis consultas
    </a>

    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Tu compra</span>
            <h1>Dejar reseña</h1>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3 mb-3">
                @if ($portada)
                    <img src="{{ $portada->url }}" class="miniatura-vehiculo" alt="{{ $v->marca }} {{ $v->modelo }}">
                @endif
                <div>
                    <div class="fw-bold fs-5">{{ $v->marca }} {{ $v->modelo }}</div>
                    <div class="text-muted small">{{ $v->anio }}</div>
                </div>
            </div>

            <form action="{{ route('resenas.store', $consulta) }}" method="POST">
                @csrf
                <label for="texto" class="form-label">Contanos cómo fue tu compra</label>
                <textarea id="texto" name="texto" rows="5" maxlength="{{ Resena::TEXTO_MAX }}" required
                          class="form-control @error('texto') is-invalid @enderror"
                          placeholder="Por ejemplo: cómo te atendieron, si el auto estaba como en las fotos, cómo fue la entrega...">{{ old('texto') }}</textarea>
                @error('texto') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                <div class="form-text">
                    Entre {{ Resena::TEXTO_MIN }} y {{ Resena::TEXTO_MAX }} caracteres.
                    Se publica con tu primer nombre cuando el administrador la aprueba.
                </div>

                <button type="submit" class="btn btn-acento mt-3">Enviar reseña</button>
            </form>
        </div>
    </div>
@endsection
