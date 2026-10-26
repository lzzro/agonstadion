<?php
# Las reglas de un partido: series al mejor de N, el empate y el walkover.

use PHPUnit\Framework\TestCase;

final class ConfiguracionTorneoTest extends TestCase
{
    public function testUnaSerieSeGanaConLaMitadMasUno(): void
    {
        $this->assertSame(2, (new ConfiguracionTorneo(1, 3, 1, 0, 0, 0, 0, null, null, 'diferencia', 3))->ganaSerieCon());
        $this->assertSame(4, (new ConfiguracionTorneo(1, 3, 1, 0, 0, 0, 0, null, null, 'diferencia', 7))->ganaSerieCon());
        $this->assertNull((new ConfiguracionTorneo(1))->ganaSerieCon());
    }

    public function testAlMejorDeTresSoloValenCuatroMarcadores(): void
    {
        $serie = new ConfiguracionTorneo(1, 3, 1, 0, 0, 0, 0, null, null, 'diferencia', 3);
        $validos = array();
        for ($l = 0; $l <= 4; $l++) {
            for ($v = 0; $v <= 4; $v++) {
                if (empty($serie->erroresMarcador($l, $v))) { $validos[] = "$l-$v"; }
            }
        }
        $this->assertSame(array('0-2', '1-2', '2-0', '2-1'), $validos);
    }

    public function testElEmpateSegunLaLiga(): void
    {
        $this->assertSame(array(), (new ConfiguracionTorneo(1))->erroresMarcador(1, 1));
        $this->assertNotEmpty((new ConfiguracionTorneo(1, 3, 1, 0, 0))->erroresMarcador(1, 1));
    }

    public function testUnaSerieNoAdmiteEmpate(): void
    {
        $this->assertNotEmpty((new ConfiguracionTorneo(1, 3, 1, 0, 1, 0, 0, null, null, 'diferencia', 3))->validar());
        $this->assertNotEmpty((new ConfiguracionTorneo(1, 3, 1, 0, 0, 0, 0, null, null, 'diferencia', 4))->validar());
    }

    public function testUnWalkoverNoSeMiraPorElMarcador(): void
    {
        $serie = new ConfiguracionTorneo(1, 3, 1, 0, 0, 0, 0, null, null, 'diferencia', 3);
        $this->assertSame(array(), $serie->erroresMarcador(0, 0, true));
    }

    public function testCadaLadoDeCeroAQuinientos(): void
    {
        $unico = new ConfiguracionTorneo(1);
        $this->assertNotEmpty($unico->erroresMarcador(-1, 0));
        $this->assertNotEmpty($unico->erroresMarcador(0, 501));
        $this->assertSame(array(), $unico->erroresMarcador(500, 0));
    }
}
