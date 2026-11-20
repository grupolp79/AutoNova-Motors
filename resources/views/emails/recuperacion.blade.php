{{-- Correo de "¿Olvidaste tu contraseña?" en SOLO TEXTO. Se usa {!! !!} porque no es una página HTML:
     el texto se muestra tal cual y escaparlo cambiaría, por ejemplo, un apóstrofo del nombre por &#039;. --}}
Hola, {!! $nombre !!}:

Recibimos un pedido para crear una contraseña nueva para tu cuenta de AutoNova Motors.

Tu código es:

{{ $codigo }}

Escribilo en la pantalla «Crear contraseña nueva» junto con tu correo y la contraseña que quieras.

Vence en {{ $minutos }} minutos y se puede usar una sola vez.
Si no lo pediste, no tenés que hacer nada: tu contraseña sigue siendo la misma.
No compartas este código con nadie.

Saludos,
El equipo de AutoNova Motors
