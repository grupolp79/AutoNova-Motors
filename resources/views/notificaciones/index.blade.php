@extends('layouts.app')

@section('titulo', 'Avisos')

@section('contenido')
    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Tu cuenta</span>
            <h1>Avisos</h1>
        </div>
        @if ($noLeidas > 0)
            <form action="{{ route('notificaciones.leer-todas') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-check2-all" aria-hidden="true"></i> Marcar todos como leídos ({{ $noLeidas }})
                </button>
            </form>
        @endif
    </div>

    @if ($notificaciones->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-bell display-5 text-muted" aria-hidden="true"></i>
                <h2 class="h5 mt-3">No tenés avisos todavía</h2>
                <p class="text-muted mb-0">
                    @if (auth()->user()->puedeGestionar())
                        Acá vas a ver cuando entre una consulta nueva.
                    @else
                        Acá vas a ver cuando el equipo avance o se comunique por tus consultas.
                    @endif
                </p>
            </div>
        </div>
    @else
        <div class="card avisos-lista">
            @foreach ($notificaciones as $aviso)
                @include('notificaciones._aviso', ['aviso' => $aviso])
            @endforeach
        </div>

        <div class="mt-4">{{ $notificaciones->links() }}</div>
    @endif
@endsection
