<?php
# La eliminacion directa y el suizo (RF-19 y RF-20).

use PHPUnit\Framework\TestCase;

final class LlaveYSuizoTest extends TestCase
{
    private function equipos($n)
    {
        $lista = array();
        for ($i = 1; $i <= $n; $i++) {
            $lista[] = new Participante($i, null, new Equipo($i, 'E' . $i), 'confirmado');
        }
        return $lista;
    }

    private function nombres($cruces)
    {
        $texto = array();
        foreach ($cruces as $c) {
            $texto[] = $c['local']->getNombreVisible() . '-' . (($c['visitante'] === null) ? 'libre' : $c['visitante']->getNombreVisible());
        }
        return implode(' ', $texto);
    }

    public function testLaSiembraDeOcho(): void
    {
        $this->assertSame(array(1, 8, 4, 5, 2, 7, 3, 6), Llave::ordenSiembra(8));
    }

    public function testConSeisDosPasesLibresParaLosPrimeros(): void
    {
        $llave = new Llave($this->equipos(6));
        $this->assertSame(array(), $llave->generar());
        $this->assertSame('E1-libre E4-E5 E2-libre E3-E6', $this->nombres($llave->getCruces()));
    }

    public function testDeCuatroATreintaYDosCadaUnoUnaVez(): void
    {
        for ($n = 4; $n <= 32; $n++) {
            $llave = new Llave($this->equipos($n));
            $llave->generar();
            $vistos = array();
            foreach ($llave->getCruces() as $c) {
                $vistos[] = $c['local']->getIdParticipante();
                if ($c['visitante'] !== null) { $vistos[] = $c['visitante']->getIdParticipante(); }
            }
            sort($vistos);
            $this->assertSame(range(1, $n), $vistos, "con $n");
            $this->assertCount(Llave::tamano($n) / 2, $llave->getCruces(), "con $n");
        }
    }

    public function testLosNombresDeLasRondas(): void
    {
        $this->assertSame('Final', Llave::nombreRonda(1));
        $this->assertSame('Semifinal', Llave::nombreRonda(2));
        $this->assertSame('Cuartos de final', Llave::nombreRonda(4));
        $this->assertSame(3, Llave::cantidadRondas(6));
    }

    public function testQuienPasa(): void
    {
        $e = $this->equipos(4);
        $jugado = new Enfrentamiento(2, 2, $e[1], $e[2]);
        $jugado->asignarResultado(new Resultado(0, 2, $e[2]));
        $this->assertSame($e[0], Llave::quienPasa(new Enfrentamiento(1, 1, $e[0], null)));
        $this->assertSame($e[2], Llave::quienPasa($jugado));
        $this->assertNull(Llave::quienPasa(new Enfrentamiento(3, 3, $e[0], $e[3])));
    }

    public function testElSuizoNoRepiteRival(): void
    {
        $e = $this->equipos(4);
        $cruces = Suizo::emparejar($e, array(Suizo::clave(1, 2) => true, Suizo::clave(2, 4) => true), array());
        $this->assertSame('E1-E4 E2-E3', $this->nombres($cruces));
    }

    public function testLaPrimeraRondaDelSuizo(): void
    {
        $this->assertSame('E1-E5 E2-E6 E3-E7 E4-E8', $this->nombres(Suizo::primeraRonda($this->equipos(8))));
        $this->assertSame('E1-E3 E2-E4 E5-libre', $this->nombres(Suizo::primeraRonda($this->equipos(5))));
        $this->assertSame(3, Suizo::rondasSugeridas(8));
    }
}
