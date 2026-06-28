<?php

namespace Tests\Feature;

use App\Console\Commands\ImportCentrosAnpe;
use Tests\TestCase;

class ImportCentrosAnpeTest extends TestCase
{
    private ImportCentrosAnpe $cmd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cmd = new ImportCentrosAnpe();
    }

    public function test_parse_codi_first_handles_single_and_wrapped_rows(): void
    {
        $txt = <<<TXT
        Codi       Centre                    Adreça                  Localitat           Telèfon
        03015324 CEE LO MORANT               Calle PABLO NERUDA, 7    03010 - ALACANT     965937020
                 CEE SANTO ÁNGEL DE LA
        03010570                             Travesía VIRGEN DEL PUIG, 17   03009 - ALACANT  965937025
                 GUARDA
        TXT;

        $recs = $this->cmd->parseCodiFirst($txt);

        $this->assertCount(2, $recs);
        $this->assertSame('03015324', $recs[0]['codigo']);
        $this->assertStringContainsString('CEE LO MORANT', $recs[0]['nombre']);
        $this->assertSame('ALACANT', $recs[0]['localidad']);

        // Name wrapped onto the line before the code is still captured.
        $this->assertSame('03010570', $recs[1]['codigo']);
        $this->assertStringContainsString('CEE SANTO ÁNGEL', $recs[1]['nombre']);
    }

    public function test_parse_ueco(): void
    {
        $txt = <<<TXT
        Codi       Centre                                  Localitat
        03009683                      CEIP Azorín                       ALACANT / ALICANTE
        46026329                      CEIP El Castell                   XÀTIVA / JÁTIVA
        TXT;

        $recs = $this->cmd->parseUeco($txt);

        $this->assertCount(2, $recs);
        $this->assertSame('03009683', $recs[0]['codigo']);
        $this->assertSame('CEIP Azorín', $recs[0]['nombre']);
        $this->assertSame('ALACANT', $recs[0]['localidad']);
    }

    public function test_parse_jornada_continuada(): void
    {
        $txt = <<<TXT
                  LOCALITAT    CODI                         CENTRE
        AGOST                 3000047   CEIP LA RAMBLA
        ALACANT               3009683   CEIP AZORÍN
        TXT;

        $recs = $this->cmd->parseJornadaContinuada($txt);

        $this->assertCount(2, $recs);
        // Header line is skipped.
        $this->assertSame('3000047', $recs[0]['codigo']);
        $this->assertSame('CEIP LA RAMBLA', $recs[0]['nombre']);
        $this->assertSame('AGOST', $recs[0]['localidad']);
    }

    public function test_parse_penitenciaris_label_blocks(): void
    {
        $txt = <<<TXT
        PROVÍNCIA D'ALACANT
        Localitat: Villena
        Denominació: CENTRO PÚBLICO DE FORMACIÓN DE PERSONAS
        ADULTAS LA ATALAYA
        Codi: 03017497
        Adreça: Establiment Penitenciari de Villena
        Composició: 19 unitats
        TXT;

        $recs = $this->cmd->parsePenitenciaris($txt);

        $this->assertCount(1, $recs);
        $this->assertSame('03017497', $recs[0]['codigo']);
        $this->assertSame('Villena', $recs[0]['localidad']);
        $this->assertStringContainsString('LA ATALAYA', $recs[0]['nombre']);
    }
}
