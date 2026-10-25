<?php
# Las cuentas (RF-01, RF-02) y las reglas de un torneo que cruzan su
# formato con su configuracion (RF-14, RF-17).

use PHPUnit\Framework\TestCase;

final class CuentaYTorneoTest extends TestCase
{
    public function testLaClaveSeGuardaSoloComoHash(): void
    {
        $u = new Usuario(null, 'alguien@ejemplo.invalid', null, 'Ariadna', 'Minos');
        $this->assertSame(array(), $u->asignarClave('Una clave larga 7'));
        $this->assertNotSame('Una clave larga 7', $u->getHashPassword());
        $this->assertTrue($u->verificarClave('Una clave larga 7'));
        $this->assertFalse($u->verificarClave('Otra clave larga 7'));
        $this->assertNotEmpty($u->asignarClave('corta'));
    }

    # La politica de contrasenas (Ciberseguridad, tercera entrega): una
    # mayuscula, una minuscula y un numero, y nada del nombre, el
    # apellido ni el correo. Una clave rechazada no cambia el hash.
    public function testLaClaveCumpleLaPolitica(): void
    {
        $u = new Usuario(null, 'teseo@ejemplo.invalid', null, 'Ariadna', 'Minos');
        $this->assertSame(array(), $u->asignarClave('Laberinto de 7 vueltas'));
        $hash = $u->getHashPassword();
        $this->assertNotEmpty($u->asignarClave('sin mayusculas 7'));
        $this->assertNotEmpty($u->asignarClave('SIN MINUSCULAS 7'));
        $this->assertNotEmpty($u->asignarClave('Sin numeros nunca'));
        $this->assertNotEmpty($u->asignarClave('Soy ARIADNA 2026'));
        $this->assertNotEmpty($u->asignarClave('Rey minos 12345'));
        $this->assertNotEmpty($u->asignarClave('Hilo de Teseo 1'));
        $this->assertSame($hash, $u->getHashPassword());
        $this->assertTrue($u->verificarClave('Laberinto de 7 vueltas'));
        $this->assertSame(array(), $u->asignarClave('Ñandú veloz 2026'));
    }

    public function testElCorreoTieneQueSerUnCorreo(): void
    {
        $this->assertNotEmpty((new Usuario(null, 'no-es-un-correo', 'x', 'Ariadna', 'Minos'))->validar());
    }

    public function testLosNombresSeMuestranConMayuscula(): void
    {
        $this->assertSame('Ariadna Minos', (new Usuario(1, null, null, 'ariadna', 'minos'))->getNombreCompletoVisible());
        $this->assertSame('Odiseo de Ítaca', (new Usuario(1, null, null, 'odiseo', 'de Ítaca'))->getNombreCompletoVisible());
    }

    private function torneo($modulo, $cupo = 8)
    {
        return new Torneo(1, 'Prueba', new Disciplina(1, 'Ajedrez'), new TipoTorneo(1, 'Individual', 0),
                          new ModuloCompetencia(1, $modulo), new Usuario(1, null, null, 'Olga', 'Organiza'),
                          null, null, $cupo, null, 'inscripcion');
    }

    public function testSoloEnUnaLigaClasificanAPlayoffs(): void
    {
        $con_playoffs = new ConfiguracionTorneo(null, 3, 1, 0, 0, 4);
        $this->assertSame(array(), $this->torneo('Liga')->validarConfiguracion($con_playoffs));
        $this->assertSame(array('Solo en una liga clasifican a playoffs.'), $this->torneo('Suizo')->validarConfiguracion($con_playoffs));
        $this->assertSame(array('A los playoffs no clasifican más que el cupo.'),
                          $this->torneo('Liga', 4)->validarConfiguracion(new ConfiguracionTorneo(null, 3, 1, 0, 0, 8)));
    }

    public function testUnaEliminacionDirectaNoAdmiteEmpate(): void
    {
        $this->assertNotEmpty($this->torneo('Eliminación directa')->validarConfiguracion(new ConfiguracionTorneo(null)));
    }

    public function testUnTorneoCanceladoNoSeCancelaOtraVez(): void
    {
        $t = $this->torneo('Liga');
        $this->assertSame(array(), $t->cancelar());
        $this->assertNotEmpty($t->cancelar());
    }

    public function testElDorsalVaDeCeroANoventaYNueve(): void
    {
        $u = new Usuario(1, null, null, 'Orfeo', 'Tracia');
        $this->assertSame(array(), (new IntegranteEquipo($u, 99))->validar());
        $this->assertSame(array('El dorsal va de 0 a 99.'), (new IntegranteEquipo($u, 100))->validar());
    }
}
