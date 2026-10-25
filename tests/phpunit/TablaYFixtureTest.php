<?php
# El fixture de una liga (metodo del circulo) y la tabla de posiciones,
# que se calcula de los partidos y no se guarda (RF-18 y RF-22).

use PHPUnit\Framework\TestCase;

final class TablaYFixtureTest extends TestCase
{
    private function equipos($n)
    {
        $lista = array();
        for ($i = 1; $i <= $n; $i++) {
            $lista[$i] = new Participante($i, null, new Equipo($i, 'E' . $i), 'confirmado');
        }
        return $lista;
    }

    public function testCadaParejaUnaVezPorVuelta(): void
    {
        foreach (array(4, 5, 8, 9) as $n) {
            $fixture = new Fixture(array_values($this->equipos($n)));
            $this->assertSame(array(), $fixture->generar());
            $parejas = array();
            foreach ($fixture->getFechas() as $fecha) {
                foreach ($fecha as $cruce) {
                    if ($cruce['visitante'] === null) { continue; }
                    $a = $cruce['local']->getIdParticipante();
                    $b = $cruce['visitante']->getIdParticipante();
                    $parejas[min($a, $b) . '-' . max($a, $b)] = true;
                }
            }
            $this->assertCount($n * ($n - 1) / 2, $parejas, "con $n");
        }
    }

    public function testLaTablaSeOrdenaPorPuntosYCuentaPorElGanador(): void
    {
        $e = $this->equipos(3);
        $config = new ConfiguracionTorneo(1, 3, 1, 0, 1);
        $ronda = new Ronda(1, 1);
        $p1 = new Enfrentamiento(1, 1, $e[1], $e[2]);
        $p1->asignarResultado(new Resultado(2, 1, $e[1]));
        $p2 = new Enfrentamiento(2, 2, $e[2], $e[3]);
        $p2->asignarResultado(new Resultado(0, 0, $e[3], 1));   # walkover para el 3
        $ronda->cargarEnfrentamiento($p1);
        $ronda->cargarEnfrentamiento($p2);
        $tabla = new TablaPosiciones($config, $e);
        $tabla->sumarRondas(array($ronda));
        $orden = array();
        foreach ($tabla->getOrdenadas() as $fila) {
            $orden[] = $fila->getNombreVisible() . ':' . $fila->getPuntos($config);
        }
        $this->assertSame(array('E1:3', 'E3:3', 'E2:0'), $orden);
    }

    public function testElLibreSumaSoloEnElSuizo(): void
    {
        $e = $this->equipos(2);
        $ronda = new Ronda(1, 1);
        $ronda->cargarEnfrentamiento(new Enfrentamiento(1, 1, $e[1], null));
        $suizo = new TablaPosiciones(new ConfiguracionTorneo(1), $e, true);
        $liga  = new TablaPosiciones(new ConfiguracionTorneo(1), $e);
        $suizo->sumarRondas(array($ronda));
        $liga->sumarRondas(array($ronda));
        $this->assertSame(1, $suizo->getPosiciones()[1]->getGanados());
        $this->assertSame(0, $liga->getPosiciones()[1]->getGanados());
    }
}
