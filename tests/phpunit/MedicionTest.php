<?php
# Los tres modelos de Fisica del rendimiento (RF-29), con los valores de
# la segunda entrega de Fisica.

use PHPUnit\Framework\TestCase;

final class MedicionTest extends TestCase
{
    private function medicion($prueba, $d, $t, $m, $fecha = '2026-10-01')
    {
        return new Medicion(null, new Usuario(1, null, null, 'Hipatia', 'Alejandría'), $prueba, $d, $t, $m, $fecha);
    }

    public function testTiempoDeReaccionPorCaidaLibre(): void
    {
        $this->assertEqualsWithDelta(127.8, $this->medicion('reaccion', 8, null, null)->tiempoReaccionMs(), 0.05);
        $this->assertSame('212', Medicion::formatear('reaccion', $this->medicion('reaccion', 22, null, null)->indicador()));
    }

    public function testSprintAceleracionYFuerza(): void
    {
        $m = $this->medicion('sprint', null, 2.30, 68);
        $this->assertEqualsWithDelta(4.348, $m->velocidadMedia(), 0.001);
        $this->assertEqualsWithDelta(3.781, $m->aceleracion(), 0.001);
        $this->assertEqualsWithDelta(257.1, $m->fuerza(), 0.05);
        $this->assertSame('3,78', Medicion::formatear('sprint', $m->indicador()));
    }

    public function testPotenciaDeSalto(): void
    {
        $m = $this->medicion('salto', null, 0.45, 60);
        $this->assertEqualsWithDelta(0.2481, $m->altura(), 0.0001);
        $this->assertEqualsWithDelta(145.86, $m->energiaPotencial(), 0.01);
        $this->assertEqualsWithDelta(583.4, $m->potencia(), 0.05);
        $this->assertSame('381', Medicion::formatear('salto', $this->medicion('salto', null, 0.38, 55)->indicador()));
    }

    public function testCadaPruebaCalculaSoloLoSuyo(): void
    {
        $this->assertNull($this->medicion('reaccion', 8, null, null)->potencia());
        $this->assertNull($this->medicion('sprint', null, 2.3, 68)->tiempoReaccionMs());
    }

    public function testLaMejorMarca(): void
    {
        $this->assertTrue(Medicion::esMejor('reaccion', 120, 128));
        $this->assertTrue(Medicion::esMejor('salto', 600, 583));
        $this->assertFalse(Medicion::esMejor('sprint', 3.5, 3.78));
    }

    public function testLosRangosSonLosDeLaBase(): void
    {
        $this->assertSame(array(), $this->medicion('reaccion', 30, null, null)->validar());
        $this->assertSame(array('La regla mide 30 cm: la distancia va de 0,5 a 30.'), $this->medicion('reaccion', 30.1, null, null)->validar());
        $this->assertNotEmpty($this->medicion('sprint', null, 2.3, null)->validar());
        $this->assertNotEmpty($this->medicion('salto', null, 1.2, 60)->validar());
        $this->assertNotEmpty($this->medicion('natacion', 10, null, null)->validar());
        $this->assertNotEmpty($this->medicion('reaccion', 8, null, null, '2026-02-30')->validar());
    }
}
