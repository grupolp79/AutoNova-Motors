@extends('layouts.app')

@section('titulo', 'Mis favoritos')

@section('contenido')
    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Tu cuenta</span>
            <h1>Mis favoritos</h1>
        </div>
    </div>

    @if ($vehiculos->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-heart display-5 text-muted" aria-hidden="true"></i>
                <h2 class="h5 mt-3">Todavía no guardaste ningún vehículo</h2>
                <p class="text-muted">En la página de cada vehículo está el botón «Guardar en favoritos».</p>
                <a href="{{ route('vehiculos.index') }}" class="btn btn-acento">Ver el catálogo</a>
            </div>
        </div>
    @else
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            @foreach ($vehiculos as $vehiculo)
                @php $portada = $vehiculo->fotos->first(); @endphp
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        @if ($portada)
                            <img src="{{ $portada->url }}" class="card-img-top object-fit-cover" style="height: 180px"
                                 alt="{{ $vehiculo->marca }} {{ $vehiculo->modelo }}">
                        @else
                            <div class="bg-secondary-subtle d-flex align-items-center justify-content-center text-muted"
                                 style="height: 180px">Sin foto</div>
                        @endif

                        <div class="card-body">
                            <div class="mb-2">@include('vehiculos._badges')</div>
                            <h5 class="card-title mb-1">{{ $vehiculo->marca }} {{ $vehiculo->modelo }}</h5>
                            <p class="card-text text-muted small mb-2">
                                {{ $vehiculo->anio }} ·
                                {{ $vehiculo->kilometraje == 0 ? '0 km' : number_format($vehiculo->kilometraje, 0, ',', '.') . ' km' }}
                            </p>
                            <p class="card-text fs-5 fw-bold mb-0">U$S {{ number_format($vehiculo->precio, 0, ',', '.') }}</p>
                        </div>
                        <div class="card-footer bg-transparent border-0 pb-3 d-flex gap-2">
                            <a href="{{ route('vehiculos.show', $vehiculo) }}" class="btn btn-outline-dark btn-sm flex-grow-1">Ver detalle</a>
                            <form action="{{ route('favoritos.destroy', $vehiculo) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Quitar de favoritos">
                                    <i class="bi bi-heart-fill" aria-hidden="true"></i>
                                    <span class="visually-hidden">Quitar de favoritos</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $vehiculos->links() }}</div>
    @endif
@endsection
