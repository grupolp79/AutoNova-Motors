{{--
    Formulario compartido por create y edit.
    old('campo', valor) muestra lo que escribió el usuario si falló la validación;
    si no, el valor actual del vehículo (vacío cuando es nuevo).
--}}
@php
    // En el textarea van SOLO las fotos externas (URL), una por línea. Las subidas como archivo
    // se muestran aparte como miniaturas: si estuvieran acá, al guardar se validarían como URL y fallarían.
    $fotosTexto = old('fotos', $vehiculo->fotos->reject->esSubida()->pluck('ruta')->implode("\n"));

    // Errores de los archivos subidos (archivos.0, archivos.1, ...)
    $erroresArchivos = array_merge($errors->get('archivos'), \Illuminate\Support\Arr::flatten($errors->get('archivos.*')));
    $quitarElegidas = array_map('intval', old('quitar', []));

    // Un checkbox desmarcado no se envía; si hubo error se respeta lo que eligió el usuario
    $destacado = $errors->any() ? old('destacado') : $vehiculo->destacado;

    // Errores de las líneas de fotos (vienen como lista_fotos.0, lista_fotos.1, ...)
    $erroresFotos = array_merge(
        $errors->get('fotos'),
        $errors->get('lista_fotos'),
        \Illuminate\Support\Arr::flatten($errors->get('lista_fotos.*'))
    );
@endphp

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="marca" class="form-label">Marca</label>
                <input type="text" id="marca" name="marca" maxlength="60" required
                       class="form-control @error('marca') is-invalid @enderror"
                       value="{{ old('marca', $vehiculo->marca) }}">
                @error('marca') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="modelo" class="form-label">Modelo</label>
                <input type="text" id="modelo" name="modelo" maxlength="60" required
                       class="form-control @error('modelo') is-invalid @enderror"
                       value="{{ old('modelo', $vehiculo->modelo) }}">
                @error('modelo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label for="anio" class="form-label">Año</label>
                <input type="number" id="anio" name="anio" min="1900" max="{{ date('Y') + 1 }}" required
                       class="form-control @error('anio') is-invalid @enderror"
                       value="{{ old('anio', $vehiculo->anio) }}">
                @error('anio') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label for="precio" class="form-label">Precio (U$S)</label>
                <input type="number" id="precio" name="precio" min="0" step="0.01" required
                       class="form-control @error('precio') is-invalid @enderror"
                       value="{{ old('precio', $vehiculo->precio) }}">
                @error('precio') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label for="kilometraje" class="form-label">Kilometraje</label>
                <input type="number" id="kilometraje" name="kilometraje" min="0" required
                       class="form-control @error('kilometraje') is-invalid @enderror"
                       value="{{ old('kilometraje', $vehiculo->kilometraje ?? 0) }}">
                @error('kilometraje') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            {{-- Los <select> se arman con las constantes del modelo: clave = valor del enum --}}
            <div class="col-md-4">
                <label for="transmision" class="form-label">Transmisión</label>
                <select id="transmision" name="transmision" required
                        class="form-select @error('transmision') is-invalid @enderror">
                    <option value="">Elegir...</option>
                    @foreach (\App\Models\Vehiculo::TRANSMISIONES as $valor => $texto)
                        <option value="{{ $valor }}" @selected(old('transmision', $vehiculo->transmision) === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
                @error('transmision') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label for="combustible" class="form-label">Combustible</label>
                <select id="combustible" name="combustible" required
                        class="form-select @error('combustible') is-invalid @enderror">
                    <option value="">Elegir...</option>
                    @foreach (\App\Models\Vehiculo::COMBUSTIBLES as $valor => $texto)
                        <option value="{{ $valor }}" @selected(old('combustible', $vehiculo->combustible) === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
                @error('combustible') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label for="estado" class="form-label">Estado</label>
                <select id="estado" name="estado" required
                        class="form-select @error('estado') is-invalid @enderror">
                    @foreach (\App\Models\Vehiculo::ESTADOS as $valor => $texto)
                        <option value="{{ $valor }}" @selected(old('estado', $vehiculo->estado) === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
                @error('estado') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            {{-- Destacados: solo el Admin (RF-12). Para el Empleado el campo no se muestra. --}}
            @if (auth()->user()?->esAdmin())
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" id="destacado" name="destacado" value="1" class="form-check-input"
                               @checked($destacado)>
                        <label for="destacado" class="form-check-label">Destacado (aparece primero en el catálogo)</label>
                    </div>
                </div>
            @endif

            {{-- Mini descripción (opcional). Si se deja vacía, el catálogo muestra un texto armado con los datos --}}
            <div class="col-12">
                <label for="descripcion" class="form-label">Descripción corta (opcional)</label>
                <textarea id="descripcion" name="descripcion" rows="3" maxlength="{{ \App\Models\Vehiculo::DESCRIPCION_MAX }}"
                          class="form-control @error('descripcion') is-invalid @enderror"
                          placeholder="Por ejemplo: SUV cómoda y alta, con el service al día. Ideal para la familia.">{{ old('descripcion', $vehiculo->descripcion) }}</textarea>
                <div class="form-text d-flex justify-content-between gap-2">
                    <span>Se ve resumida en el catálogo y completa en el detalle.</span>
                    {{-- Contador: lo actualiza el script de abajo mientras se escribe --}}
                    <span><span id="contadorDescripcion">{{ mb_strlen(old('descripcion', $vehiculo->descripcion) ?? '') }}</span>/{{ \App\Models\Vehiculo::DESCRIPCION_MAX }}</span>
                </div>
                @error('descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            {{-- Fotos actuales (solo al editar): miniaturas con la casilla "Quitar" --}}
            @if ($vehiculo->exists && $vehiculo->fotos->isNotEmpty())
                <div class="col-12">
                    <span class="form-label d-block">Fotos actuales</span>
                    <div class="fotos-actuales">
                        @foreach ($vehiculo->fotos as $foto)
                            <div class="foto-actual">
                                <img src="{{ $foto->url }}" alt="Foto {{ $loop->iteration }} de {{ $vehiculo->marca }} {{ $vehiculo->modelo }}" loading="lazy">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" name="quitar[]" value="{{ $foto->id }}"
                                           id="quitar{{ $foto->id }}" @checked(in_array($foto->id, $quitarElegidas, true))>
                                    <label class="form-check-label small" for="quitar{{ $foto->id }}">Quitar</label>
                                </div>
                                <span class="badge text-bg-light border">{{ $foto->esSubida() ? 'Archivo' : 'URL' }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-text">Las fotos se muestran en este orden; la primera es la portada. Las nuevas se agregan al final.</div>
                </div>
            @endif

            <div class="col-md-6">
                <label for="archivos" class="form-label">Subir fotos</label>
                {{-- accept solo filtra lo que ofrece el navegador; el servidor vuelve a controlar el tipo real --}}
                <input type="file" id="archivos" name="archivos[]" multiple accept="image/jpeg,image/png,image/webp"
                       class="form-control @if ($erroresArchivos) is-invalid @endif">
                <div class="form-text">JPG, PNG o WebP, hasta 5 MB cada una. Hasta {{ \App\Models\Vehiculo::MAX_FOTOS }} fotos por vehículo entre archivos y URLs.</div>
                @foreach ($erroresArchivos as $error)
                    <div class="invalid-feedback">{{ $error }}</div>
                @endforeach
            </div>

            <div class="col-md-6">
                <label for="fotos" class="form-label">Fotos externas (una URL por línea)</label>
                <textarea id="fotos" name="fotos" rows="4"
                          class="form-control font-monospace @if ($erroresFotos) is-invalid @endif"
                          placeholder="https://ejemplo.com/foto1.jpg">{{ $fotosTexto }}</textarea>
                <div class="form-text">Si borrás una línea, esa foto se quita. Las fotos subidas como archivo no aparecen acá.</div>
                @foreach ($erroresFotos as $error)
                    <div class="invalid-feedback">{{ $error }}</div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        // Contador de caracteres de la descripción: se actualiza con cada tecla
        (function () {
            const campo = document.getElementById('descripcion');
            const contador = document.getElementById('contadorDescripcion');
            campo.addEventListener('input', () => contador.textContent = campo.value.length);
        })();
    </script>
@endpush
