{{--
    Panel "Descripción" de la ficha del vehículo. Recibe $vehiculo y usa $gestion (Empleado o Admin) de show.
      visitante y cliente -> ven el panel solo si hay descripción
      empleado y admin    -> lo ven siempre ("Todavía no tiene descripción" si está vacía)
                             y tienen "Editar descripción", que abre el formulario sin salir de la ficha
    Los permisos se controlan en el servidor (ruta vehiculos.descripcion, middleware rol:empleado,admin).
--}}
@php
    $texto = filled($vehiculo->descripcion) ? trim($vehiculo->descripcion) : null;
    // Si volvió con errores de la edición rápida, el formulario se muestra abierto
    $conErrores = $errors->getBag('descripcion')->any();
@endphp

@if ($texto !== null || $gestion)
    <section class="ficha-descripcion card panel-descripcion" aria-labelledby="tituloDescripcion">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h2 id="tituloDescripcion" class="h5 mb-0">Descripción</h2>
                @if ($gestion)
                    <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="collapse"
                            data-bs-target="#editarDescripcion" aria-expanded="{{ $conErrores ? 'true' : 'false' }}"
                            aria-controls="editarDescripcion">
                        <i class="bi bi-pencil" aria-hidden="true"></i> Editar descripción
                    </button>
                @endif
            </div>

            {{-- {{ }} escapa el texto (si alguien escribió HTML se ve como texto); pre-line respeta los saltos de línea --}}
            @if ($texto !== null)
                <p class="texto-descripcion mb-0">{{ $texto }}</p>
            @else
                <p class="text-muted fst-italic mb-0">Todavía no tiene descripción</p>
            @endif

            @if ($gestion)
                {{-- Edición rápida: un collapse de Bootstrap con el formulario (sin JS propio para abrir y cerrar) --}}
                <div class="collapse {{ $conErrores ? 'show' : '' }}" id="editarDescripcion">
                    <form action="{{ route('vehiculos.descripcion', $vehiculo) }}" method="POST" class="mt-3">
                        @csrf
                        @method('PATCH')
                        <label for="descripcionRapida" class="form-label visually-hidden">Descripción</label>
                        <textarea id="descripcionRapida" name="descripcion" rows="5" maxlength="{{ \App\Models\Vehiculo::DESCRIPCION_MAX }}"
                                  class="form-control @error('descripcion', 'descripcion') is-invalid @enderror"
                                  placeholder="Por ejemplo: único dueño, service al día, cubiertas nuevas...">{{ $conErrores ? old('descripcion') : $texto }}</textarea>
                        @error('descripcion', 'descripcion') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        <div class="form-text">
                            <span id="contadorRapida">0</span> / {{ \App\Models\Vehiculo::DESCRIPCION_MAX }} caracteres. Si la dejás vacía, se borra.
                        </div>

                        <div class="d-flex gap-2 mt-2">
                            <button type="submit" class="btn btn-acento btn-sm">Guardar</button>
                            <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="collapse" data-bs-target="#editarDescripcion">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>

                @push('scripts')
                    <script>
                        // Contador de caracteres de la edición rápida
                        (function () {
                            const campo = document.getElementById('descripcionRapida');
                            const contador = document.getElementById('contadorRapida');
                            const actualizar = () => contador.textContent = campo.value.length;
                            campo.addEventListener('input', actualizar);
                            actualizar();
                        })();
                    </script>
                @endpush
            @endif
        </div>
    </section>
@endif
