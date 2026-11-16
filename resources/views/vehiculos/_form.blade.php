{{--
    Formulario compartido por create y edit.
    old('campo', valor) muestra lo que escribió el usuario si falló la validación;
    si no, el valor actual del vehículo (vacío cuando es nuevo).
--}}
@php
    // Fotos actuales, una URL por línea
    $fotosTexto = old('fotos', $vehiculo->fotos->pluck('ruta')->implode("\n"));

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

            <div class="col-12">
                <div class="form-check">
                    <input type="checkbox" id="destacado" name="destacado" value="1" class="form-check-input"
                           @checked($destacado)>
                    <label for="destacado" class="form-check-label">Destacado (aparece primero en el catálogo)</label>
                </div>
            </div>

            <div class="col-12">
                <label for="fotos" class="form-label">Fotos (una URL por línea)</label>
                <textarea id="fotos" name="fotos" rows="5"
                          class="form-control font-monospace @if ($erroresFotos) is-invalid @endif"
                          placeholder="https://ejemplo.com/foto1.jpg">{{ $fotosTexto }}</textarea>
                <div class="form-text">La primera línea es la foto de portada. Al guardar se reemplazan todas las fotos del vehículo.</div>
                @foreach ($erroresFotos as $error)
                    <div class="invalid-feedback">{{ $error }}</div>
                @endforeach
            </div>
        </div>
    </div>
</div>
