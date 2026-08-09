<?php

use App\Actions\Study\Exceptions\InvalidTrackImportException;
use App\Actions\Study\ParseTrackImport;

function parseTrack(string $content): array
{
    return (new ParseTrackImport)->handle($content);
}

test('it reads the four columns of a spreadsheet paste', function () {
    $rows = parseTrack(implode("\n", [
        "0\t1\tBanco de Dados\tEstudo/Revisão da Aula 00 e Resolução de Questões do PDF.",
        "1\t2\tRaciocínio Lógico\tEstudo da Aula 00 (Estruturas Lógicas I).",
    ]));

    expect($rows)->toBe([
        ['cycle' => 0, 'subject' => 'Banco de Dados', 'name' => 'Estudo/Revisão da Aula 00 e Resolução de Questões do PDF.'],
        ['cycle' => 1, 'subject' => 'Raciocínio Lógico', 'name' => 'Estudo da Aula 00 (Estruturas Lógicas I).'],
    ]);
});

test('it accepts two columns for a hand-written list', function () {
    $rows = parseTrack("Português\tAula 01\nInglês\tAula 02");

    expect($rows)->toBe([
        ['cycle' => null, 'subject' => 'Português', 'name' => 'Aula 01'],
        ['cycle' => null, 'subject' => 'Inglês', 'name' => 'Aula 02'],
    ]);
});

test('it accepts semicolons, because task titles are full of commas', function () {
    $rows = parseTrack('0;1;Banco de Dados;Estudo da Aula 00, com questões, do PDF');

    expect($rows[0]['subject'])->toBe('Banco de Dados')
        ->and($rows[0]['name'])->toBe('Estudo da Aula 00, com questões, do PDF');
});

test('it drops the spreadsheet header line', function () {
    $rows = parseTrack(implode("\n", [
        "TRILHA\tTAREFA\tDISCIPLINA\tTÍTULO DA TAREFA",
        "0\t1\tBanco de Dados\tAula 00",
    ]));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('Aula 00');
});

test('it keeps a task that merely mentions the word discipline', function () {
    $rows = parseTrack("Banco de Dados\tDisciplina de modelagem");

    expect($rows)->toHaveCount(1);
});

test('it ignores blank lines and stray carriage returns', function () {
    $rows = parseTrack("Português\tAula 01\r\n\r\n   \r\nInglês\tAula 02");

    expect($rows)->toHaveCount(2);
});

test('an empty cycle column means the current cycle', function () {
    $rows = parseTrack("\t1\tPortuguês\tAula 01");

    expect($rows[0]['cycle'])->toBeNull();
});

test('it rejects a line with the wrong number of columns', function () {
    parseTrack("0\tBanco de Dados\tAula 00");
})->throws(InvalidTrackImportException::class, 'Linha 1');

test('it rejects a non-numeric cycle', function () {
    parseTrack("primeira\t1\tBanco de Dados\tAula 00");
})->throws(InvalidTrackImportException::class, 'Linha 1');

test('it rejects a blank subject', function () {
    parseTrack("0\t1\t\tAula 00");
})->throws(InvalidTrackImportException::class, 'Linha 1');

test('it rejects a paste with nothing but a header', function () {
    parseTrack("TRILHA\tTAREFA\tDISCIPLINA\tTÍTULO");
})->throws(InvalidTrackImportException::class);

test('it refuses a paste beyond the line limit', function () {
    $line = "0\t1\tBanco de Dados\tAula 00";

    parseTrack(implode("\n", array_fill(0, ParseTrackImport::MAX_LINES + 1, $line)));
})->throws(InvalidTrackImportException::class);
