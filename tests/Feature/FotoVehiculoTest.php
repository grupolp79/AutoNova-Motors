<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Vehiculo;
use App\Models\VehiculoFoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ArmaDatos;
use Tests\TestCase;

/**
 * Fotos subidas como archivo (además de las URLs externas).
 * Disco 'vehiculos' (public/uploads/vehiculos); en las pruebas es un disco falso: no se escribe en la carpeta real.
 *
 * Los archivos de prueba se arman con bytes de imágenes reales de 1x1 píxel y se crean como UploadedFile "de verdad"
 * (no con UploadedFile::fake()): así el tipo se detecta por el CONTENIDO, igual que en el servidor,
 * y un texto renombrado a .jpg se rechaza. Tampoco hace falta la extensión GD de PHP.
 */
#[Group('RF-01')]
#[Group('Fotos')]
class FotoVehiculoTest extends TestCase
{
    use RefreshDatabase, ArmaDatos;

    // Imágenes mínimas válidas (1x1 píxel)
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    private const JPG = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';
    private const WEBP = 'UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAwA0JaQAA3AA/vuUAAA=';

    // Cómo queda la ruta de una foto subida: /uploads/vehiculos/{id}/{40 letras y números}.{extensión real}
    private const RUTA_SUBIDA = '#^/uploads/vehiculos/(\d+)/[A-Za-z0-9]{40}\.(jpg|png|webp)$#';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('vehiculos');
    }

    private function disco()
    {
        return Storage::disk('vehiculos');
    }

    /** Archivo subido con el nombre y el contenido indicados (el tipo se detecta por el contenido). */
    private function archivo(string $nombre, string $contenido, int $error = UPLOAD_ERR_OK): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'foto');
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, $nombre, null, $error, true);
    }

    private function png(string $nombre = 'foto.png'): UploadedFile
    {
        return $this->archivo($nombre, base64_decode(self::PNG));
    }

    private function jpg(string $nombre = 'foto.jpg'): UploadedFile
    {
        return $this->archivo($nombre, base64_decode(self::JPG));
    }

    private function webp(string $nombre = 'foto.webp'): UploadedFile
    {
        return $this->archivo($nombre, base64_decode(self::WEBP));
    }

    /** Datos del formulario de edición tal como los manda la pantalla, con el textarea de URLs externas. */
    private function datosEdicion(Vehiculo $v, array $cambios = []): array
    {
        return $cambios + [
            'marca' => $v->marca, 'modelo' => $v->modelo, 'anio' => $v->anio, 'precio' => $v->precio,
            'kilometraje' => $v->kilometraje, 'transmision' => $v->transmision,
            'combustible' => $v->combustible, 'estado' => $v->estado,
            'fotos' => $v->fotos()->get()->reject->esSubida()->pluck('ruta')->implode("\n"),
        ];
    }

    /** Vehículo con una foto subida (archivo real en el disco falso) y una URL externa, en ese orden. */
    private function vehiculoConFotos(): array
    {
        $v = $this->vehiculo();
        $ruta = $this->png()->store((string) $v->id, 'vehiculos');
        $subida = $v->fotos()->create(['ruta' => VehiculoFoto::PREFIJO_SUBIDAS . $ruta, 'orden' => 0]);
        $externa = $v->fotos()->create(['ruta' => 'https://ejemplo.com/externa.jpg', 'orden' => 1]);

        return [$v, $subida, $externa, $ruta];
    }

    // ------------------------------------------------------------ Configuración

    public function test_el_disco_vehiculos_esta_dentro_de_public_sin_storage_link(): void
    {
        $this->assertSame(public_path('uploads/vehiculos'), config('filesystems.disks.vehiculos.root'));
        $this->assertSame('/uploads/vehiculos', config('filesystems.disks.vehiculos.url'));
        $this->assertSame('public', config('filesystems.disks.vehiculos.visibility'));
        $this->assertFileExists(public_path('uploads/vehiculos/.gitkeep'));
        $this->assertSame(10, Vehiculo::MAX_FOTOS);
    }

    public function test_los_formularios_tienen_el_campo_de_archivos(): void
    {
        $empleado = $this->empleado();
        [$v] = $this->vehiculoConFotos();

        foreach (['/vehiculos/create', "/vehiculos/{$v->id}/edit"] as $url) {
            $this->actingAs($empleado)->get($url)->assertOk()
                ->assertSee('enctype="multipart/form-data"', false)
                ->assertSee('type="file" id="archivos" name="archivos[]" multiple accept="image/jpeg,image/png,image/webp"', false)
                ->assertSee('JPG, PNG o WebP, hasta 5 MB cada una')
                ->assertSee('name="fotos"', false);
        }
    }

    // ------------------------------------------------------------------- Alta

    public function test_sube_jpg_png_y_webp_con_nombre_aleatorio_y_en_orden(): void
    {
        $this->actingAs($this->empleado())->post('/vehiculos', $this->datosFormulario([
            'fotos' => '',
            'archivos' => [$this->jpg('mi auto.jpg'), $this->png('frente.png'), $this->webp('costado.webp')],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $v = Vehiculo::sole();
        $fotos = $v->fotos()->get();
        $this->assertCount(3, $fotos);
        $this->assertSame([0, 1, 2], $fotos->pluck('orden')->all());

        foreach (['jpg', 'png', 'webp'] as $i => $extension) {
            $this->assertMatchesRegularExpression(self::RUTA_SUBIDA, $fotos[$i]->ruta);
            $this->assertStringEndsWith(".{$extension}", $fotos[$i]->ruta);
            $this->assertStringStartsWith("/uploads/vehiculos/{$v->id}/", $fotos[$i]->ruta);
            $this->disco()->assertExists($fotos[$i]->rutaEnDisco());
        }

        // Nunca se usa el nombre que mandó el usuario
        foreach (['mi auto', 'frente', 'costado'] as $original) {
            $this->assertStringNotContainsString($original, $fotos->pluck('ruta')->implode(' '));
        }
    }

    public function test_mezcla_url_y_archivo_en_el_mismo_guardado(): void
    {
        $this->actingAs($this->admin())->post('/vehiculos', $this->datosFormulario([
            'fotos' => "https://ejemplo.com/una.jpg\nhttps://ejemplo.com/dos.jpg",
            'archivos' => [$this->png()],
        ]))->assertSessionHasNoErrors();

        $fotos = Vehiculo::sole()->fotos()->get();
        $this->assertSame(['https://ejemplo.com/una.jpg', 'https://ejemplo.com/dos.jpg'], $fotos->take(2)->pluck('ruta')->all());
        $this->assertMatchesRegularExpression(self::RUTA_SUBIDA, $fotos[2]->ruta);
        $this->assertSame([0, 1, 2], $fotos->pluck('orden')->all());
    }

    public function test_un_nombre_malicioso_no_se_usa(): void
    {
        $this->actingAs($this->empleado())->post('/vehiculos', $this->datosFormulario([
            'fotos' => '', 'archivos' => [$this->png('../../x.php.png')],
        ]))->assertSessionHasNoErrors();

        $foto = VehiculoFoto::sole();
        $this->assertMatchesRegularExpression(self::RUTA_SUBIDA, $foto->ruta);
        $this->assertStringNotContainsString('..', $foto->ruta);
        $this->assertStringNotContainsString('x.php', $foto->ruta);

        // El único archivo escrito está dentro de la carpeta del vehículo
        $this->assertSame([$foto->rutaEnDisco()], $this->disco()->allFiles());
    }

    #[DataProvider('archivosRechazados')]
    public function test_rechaza_archivos_que_no_son_fotos_permitidas(string $nombre, string $contenido, int $error, string $mensaje): void
    {
        $this->actingAs($this->empleado())->from('/vehiculos/create')->post('/vehiculos', $this->datosFormulario([
            'fotos' => '', 'archivos' => [$this->archivo($nombre, $contenido, $error)],
        ]))->assertRedirect('/vehiculos/create')->assertSessionHasErrors(['archivos.0' => $mensaje]);

        // No queda nada: ni el vehículo ni archivos
        $this->assertSame(0, Vehiculo::count());
        $this->assertSame([], $this->disco()->allFiles());
    }

    public static function archivosRechazados(): array
    {
        $noEsImagen = 'La foto 1 no es una imagen JPG, PNG o WebP.';

        return [
            'archivo .php' => ['x.php', '<?php echo "hola"; ?>', UPLOAD_ERR_OK, $noEsImagen],
            'imagen SVG (puede llevar scripts)' => ['dibujo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', UPLOAD_ERR_OK, $noEsImagen],
            'texto renombrado a .jpg' => ['foto.jpg', 'esto es solo texto, no una foto', UPLOAD_ERR_OK, $noEsImagen],
            'más de 5 MB' => ['grande.jpg', base64_decode(self::JPG) . str_repeat("\0", 5 * 1024 * 1024 + 10), UPLOAD_ERR_OK, 'La foto 1 pesa más de 5 MB. Achicala o elegí otra.'],
            'supera el límite de PHP (upload_max_filesize)' => ['enorme.jpg', base64_decode(self::JPG), UPLOAD_ERR_INI_SIZE,
                'La foto 1 no se pudo subir: puede pesar más de lo que acepta el servidor. Probá con una de hasta 5 MB.'],
        ];
    }

    public function test_si_el_envio_supera_post_max_size_muestra_una_explicacion_y_no_un_error_500(): void
    {
        // PHP descarta todo el formulario; Laravel corta el pedido antes de validarlo (error 413)
        $respuesta = $this->actingAs($this->empleado())
            ->call('POST', '/vehiculos', [], [], [], ['CONTENT_LENGTH' => 1024 * 1024 * 1024, 'HTTP_REFERER' => url('/vehiculos/create')]);

        $respuesta->assertStatus(413)
            ->assertSee('Las fotos pesan demasiado')
            ->assertSee('no se guardó nada')
            ->assertSee(url('/vehiculos/create'), false);
        $this->assertSame(0, Vehiculo::count());
    }

    public function test_no_se_pueden_pasar_del_tope_de_10_fotos(): void
    {
        $mensaje = 'Cada vehículo puede tener hasta 10 fotos entre URLs y archivos. Quitá alguna y volvé a guardar.';
        $urls = collect(range(1, 8))->map(fn ($i) => "https://ejemplo.com/{$i}.jpg")->implode("\n");
        $empleado = $this->empleado();

        // 8 URLs + 3 archivos = 11
        $this->actingAs($empleado)->post('/vehiculos', $this->datosFormulario([
            'fotos' => $urls, 'archivos' => [$this->png(), $this->jpg(), $this->webp()],
        ]))->assertSessionHasErrors(['fotos' => $mensaje]);

        // 11 URLs solas tampoco
        $this->actingAs($empleado)->post('/vehiculos', $this->datosFormulario([
            'fotos' => collect(range(1, 11))->map(fn ($i) => "https://ejemplo.com/{$i}.jpg")->implode("\n"),
        ]))->assertSessionHasErrors(['fotos' => $mensaje]);

        $this->assertSame(0, Vehiculo::count());
        $this->assertSame([], $this->disco()->allFiles());

        // 8 + 2 = 10 sí entra
        $this->actingAs($empleado)->post('/vehiculos', $this->datosFormulario([
            'fotos' => $urls, 'archivos' => [$this->png(), $this->jpg()],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(10, VehiculoFoto::count());
    }

    public function test_al_editar_el_tope_cuenta_las_fotos_que_ya_tiene(): void
    {
        $v = $this->vehiculo();
        foreach (range(0, 8) as $i) {
            $v->fotos()->create(['ruta' => "https://ejemplo.com/{$i}.jpg", 'orden' => $i]);
        }
        $empleado = $this->empleado();
        $primera = $v->fotos()->first();

        // 9 + 2 = 11: no
        $this->actingAs($empleado)->put("/vehiculos/{$v->id}", $this->datosEdicion($v, ['archivos' => [$this->png(), $this->jpg()]]))
            ->assertSessionHasErrors('fotos');
        $this->assertSame(9, $v->fotos()->count());

        // Quitando una: 8 + 2 = 10, sí
        $this->actingAs($empleado)->put("/vehiculos/{$v->id}", $this->datosEdicion($v, ['quitar' => [$primera->id], 'archivos' => [$this->png(), $this->jpg()]]))
            ->assertSessionHasNoErrors();
        $this->assertSame(10, $v->fotos()->count());
    }

    public function test_si_falla_el_guardado_no_queda_el_vehiculo_ni_archivos_huerfanos(): void
    {
        // Sin la tabla de auditoría, el registro falla DESPUÉS de escribir los archivos
        Schema::drop('auditoria');

        try {
            $this->withoutExceptionHandling()->actingAs($this->empleado())
                ->post('/vehiculos', $this->datosFormulario(['fotos' => '', 'archivos' => [$this->png(), $this->jpg()]]));
            $this->fail('Se esperaba un error al guardar.');
        } catch (\Illuminate\Database\QueryException) {
            // esperado
        }

        $this->assertSame(0, Vehiculo::count());
        $this->assertSame(0, VehiculoFoto::count());
        $this->assertSame([], $this->disco()->allFiles());
    }

    // ---------------------------------------------------------------- Permisos

    #[DataProvider('perfiles')]
    public function test_solo_empleado_y_admin_suben_fotos(string $rol, int $esperado, bool $guarda): void
    {
        [$v] = $this->vehiculoConFotos();
        $antes = $this->disco()->allFiles();
        $actor = $rol === 'visitante' ? $this : $this->actingAs($this->{$rol}());

        $alta = $actor->post('/vehiculos', $this->datosFormulario(['fotos' => '', 'archivos' => [$this->png()]]))->assertStatus($esperado);
        $edicion = $actor->put("/vehiculos/{$v->id}", $this->datosEdicion($v, ['archivos' => [$this->jpg()]]))->assertStatus($esperado);

        if ($rol === 'visitante') {
            $alta->assertRedirect(route('login'));
            $edicion->assertRedirect(route('login'));
        }
        $this->assertSame($guarda ? 2 : 0, count($this->disco()->allFiles()) - count($antes));
    }

    public static function perfiles(): array
    {
        return [
            'visitante' => ['visitante', 302, false],
            'cliente' => ['cliente', 403, false],
            'empleado' => ['empleado', 302, true],
            'admin' => ['admin', 302, true],
        ];
    }

    public function test_un_archivo_mandado_por_otra_ruta_no_se_guarda(): void
    {
        $v = $this->vehiculo();
        $archivos = ['archivos' => [$this->png()], 'foto' => $this->png(), 'fotos' => [$this->png()]];

        $this->actingAs($this->cliente())->post("/vehiculos/{$v->id}/consultar", $archivos + ['mensaje' => 'Hola']);
        $this->actingAs($this->cliente())->post("/vehiculos/{$v->id}/favorito", $archivos);
        $this->actingAs($this->empleado())->patch("/vehiculos/{$v->id}/descripcion", $archivos + ['descripcion' => 'Texto.']);
        $this->post("/comparar/{$v->id}", $archivos);

        $this->assertSame([], $this->disco()->allFiles());
        $this->assertSame(0, VehiculoFoto::count());
    }

    // ----------------------------------------------------------------- Editar

    public function test_el_formulario_de_edicion_muestra_miniaturas_y_solo_las_urls_externas_en_el_texto(): void
    {
        [$v, $subida, $externa] = $this->vehiculoConFotos();

        $html = $this->actingAs($this->empleado())->get("/vehiculos/{$v->id}/edit")->assertOk()
            ->assertSee('Fotos actuales')
            ->assertSee('name="quitar[]" value="' . $subida->id . '"', false)
            ->assertSee('name="quitar[]" value="' . $externa->id . '"', false)
            ->assertSee(asset(ltrim($subida->ruta, '/')), false)
            ->getContent();

        // En el textarea solo está la externa
        preg_match('#<textarea id="fotos"[^>]*>(.*?)</textarea>#s', $html, $textarea);
        $this->assertSame('https://ejemplo.com/externa.jpg', trim($textarea[1]));
    }

    public function test_editar_sin_tocar_las_fotos_no_pierde_ni_duplica_ninguna(): void
    {
        [$v, $subida, $externa, $ruta] = $this->vehiculoConFotos();

        $this->actingAs($this->empleado())->put("/vehiculos/{$v->id}", $this->datosEdicion($v, ['precio' => 9999]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $fotos = $v->fotos()->get();
        $this->assertSame([$subida->id, $externa->id], $fotos->pluck('id')->all());
        $this->assertSame([0, 1], $fotos->pluck('orden')->all());
        $this->disco()->assertExists($ruta);
        $this->assertSame([$ruta], $this->disco()->allFiles());
    }

    public function test_quitar_una_foto_subida_borra_el_archivo(): void
    {
        [$v, $subida, $externa, $ruta] = $this->vehiculoConFotos();

        $this->actingAs($this->empleado())->put("/vehiculos/{$v->id}", $this->datosEdicion($v, ['quitar' => [$subida->id]]))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($subida);
        $this->assertModelExists($externa);
        $this->disco()->assertMissing($ruta);
    }

    public function test_quitar_una_url_no_toca_el_disco(): void
    {
        [$v, $subida, $externa, $ruta] = $this->vehiculoConFotos();

        // Con la casilla "Quitar"
        $this->actingAs($this->empleado())->put("/vehiculos/{$v->id}", $this->datosEdicion($v, ['quitar' => [$externa->id]]))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($externa);
        $this->assertModelExists($subida);
        $this->assertSame([$ruta], $this->disco()->allFiles());
    }

    public function test_borrar_la_linea_de_una_url_tambien_la_quita_sin_tocar_el_disco(): void
    {
        [$v, $subida, $externa, $ruta] = $this->vehiculoConFotos();

        $this->actingAs($this->empleado())->put("/vehiculos/{$v->id}", $this->datosEdicion($v, ['fotos' => '']))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($externa);
        $this->assertModelExists($subida);
        $this->disco()->assertExists($ruta);
    }

    public function test_las_existentes_mantienen_su_orden_y_las_nuevas_van_al_final(): void
    {
        [$v, $subida, $externa] = $this->vehiculoConFotos();

        $this->actingAs($this->empleado())->put("/vehiculos/{$v->id}", $this->datosEdicion($v, [
            'fotos' => "https://ejemplo.com/externa.jpg\nhttps://ejemplo.com/nueva.jpg",
            'archivos' => [$this->webp()],
        ]))->assertSessionHasNoErrors();

        $fotos = $v->fotos()->get();
        $this->assertSame($subida->id, $fotos[0]->id);
        $this->assertSame($externa->id, $fotos[1]->id);
        $this->assertSame('https://ejemplo.com/nueva.jpg', $fotos[2]->ruta);
        $this->assertStringEndsWith('.webp', $fotos[3]->ruta);
        $this->assertSame([0, 1, 2, 3], $fotos->pluck('orden')->all());
    }

    public function test_no_se_pueden_quitar_fotos_de_otro_vehiculo(): void
    {
        [$v] = $this->vehiculoConFotos();
        [$otro, $ajena, , $rutaAjena] = $this->vehiculoConFotos();

        $this->actingAs($this->empleado())->put("/vehiculos/{$v->id}", $this->datosEdicion($v, ['quitar' => [$ajena->id]]))
            ->assertSessionHasNoErrors();

        $this->assertModelExists($ajena);
        $this->disco()->assertExists($rutaAjena);
    }

    public function test_si_falla_la_edicion_se_borran_los_archivos_nuevos_y_se_conservan_los_viejos(): void
    {
        [$v, $subida, , $ruta] = $this->vehiculoConFotos();
        Schema::drop('auditoria');

        try {
            $this->withoutExceptionHandling()->actingAs($this->empleado())
                ->put("/vehiculos/{$v->id}", $this->datosEdicion($v, ['quitar' => [$subida->id], 'archivos' => [$this->jpg()]]));
            $this->fail('Se esperaba un error al guardar.');
        } catch (\Illuminate\Database\QueryException) {
            // esperado
        }

        $this->assertModelExists($subida);
        $this->assertSame([$ruta], $this->disco()->allFiles());
    }

    // --------------------------------------------------------------- Eliminar

    public function test_eliminar_el_vehiculo_borra_su_carpeta_de_fotos(): void
    {
        [$v, , , $ruta] = $this->vehiculoConFotos();
        [$otro, , , $rutaOtro] = $this->vehiculoConFotos();

        $this->actingAs($this->admin())->delete("/vehiculos/{$v->id}")->assertRedirect(route('vehiculos.index'));

        $this->disco()->assertMissing($ruta);
        $this->assertFalse($this->disco()->exists((string) $v->id));
        // Las del otro vehículo no se tocan
        $this->disco()->assertExists($rutaOtro);
    }

    public function test_un_vehiculo_con_consultas_no_pierde_nada(): void
    {
        [$v, $subida, $externa, $ruta] = $this->vehiculoConFotos();
        $this->lead($v);

        $this->actingAs($this->admin())->delete("/vehiculos/{$v->id}")->assertSessionHas('error');

        $this->assertModelExists($v);
        $this->assertModelExists($subida);
        $this->assertModelExists($externa);
        $this->disco()->assertExists($ruta);
    }

    // ------------------------------------------------------------- Auditoría

    public function test_la_auditoria_cuenta_fotos_agregadas_y_quitadas_sin_nombres_de_archivo(): void
    {
        [$v, $subida, , $ruta] = $this->vehiculoConFotos();

        $this->actingAs($this->empleado())->put("/vehiculos/{$v->id}", $this->datosEdicion($v, [
            'quitar' => [$subida->id],
            'archivos' => [$this->png('secreto-del-usuario.png'), $this->jpg()],
        ]))->assertSessionHasNoErrors();

        $detalle = Auditoria::sole()->detalle;
        $this->assertSame('Toyota Corolla 2020. Fotos: 2 agregadas, 1 quitada.', $detalle);

        $fila = json_encode(Auditoria::sole()->getAttributes());
        $this->assertStringNotContainsString('secreto-del-usuario', $fila);
        $this->assertStringNotContainsString(basename($ruta), $fila);
        foreach ($v->fotos()->get() as $foto) {
            $this->assertStringNotContainsString(basename($foto->ruta), $fila);
        }
    }

    // ---------------------------------------------------------- Dónde se ven

    public function test_las_pantallas_muestran_fotos_subidas_y_externas(): void
    {
        [$v, $subida] = $this->vehiculoConFotos();
        $src = asset(ltrim($subida->ruta, '/'));
        $cliente = $this->cliente();
        \Illuminate\Support\Facades\DB::table('favoritos')->insert(['usuario_id' => $cliente->id, 'vehiculo_id' => $v->id]);

        // Portada subida (primera foto)
        $this->get('/vehiculos')->assertSee('src="' . $src . '"', false);
        $this->get("/vehiculos/{$v->id}")->assertSee('src="' . $src . '"', false)->assertSee('src="https://ejemplo.com/externa.jpg"', false);
        $this->actingAs($cliente)->get('/favoritos')->assertSee('src="' . $src . '"', false);
        $this->withSession(['comparar' => [$v->id, $this->vehiculo()->id]])->get('/comparar')->assertSee('src="' . $src . '"', false);

        // Portada externa
        $conUrl = $this->vehiculo(['modelo' => 'Yaris']);
        $conUrl->fotos()->create(['ruta' => 'https://ejemplo.com/yaris.jpg', 'orden' => 0]);
        $this->get('/vehiculos')->assertSee('src="https://ejemplo.com/yaris.jpg"', false);
    }

    public function test_los_alt_de_las_fotos_salen_escapados(): void
    {
        [$v] = $this->vehiculoConFotos();
        $v->update(['marca' => '"><script>alert(1)</script>']);

        foreach (['/vehiculos', "/vehiculos/{$v->id}"] as $url) {
            $this->get($url)
                ->assertDontSee('alt=""><script>', false)
                ->assertDontSee('<script>alert(1)</script>', false)
                ->assertSee('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false);
        }
        $this->actingAs($this->empleado())->get("/vehiculos/{$v->id}/edit")
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('alt="Foto 1 de &quot;&gt;&lt;script&gt;', false);
    }
}
