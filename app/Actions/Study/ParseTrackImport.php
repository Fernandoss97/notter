<?php

namespace App\Actions\Study;

use App\Actions\Study\Exceptions\InvalidTrackImportException;
use Illuminate\Support\Str;

class ParseTrackImport
{
    /**
     * Teto de linhas por importação. Uma trilha de concurso inteira cabe
     * folgada; o limite existe para uma colagem acidental de um arquivo
     * grande não virar mil inserts.
     */
    public const MAX_LINES = 1000;

    /**
     * Colunas aceitas, na ordem da planilha de trilha:
     * TRILHA | TAREFA | DISCIPLINA | TÍTULO, ou só DISCIPLINA | TÍTULO
     * para quem monta a lista na mão.
     */
    private const FULL_COLUMNS = 4;

    private const SHORT_COLUMNS = 2;

    /**
     * Lê o texto colado da planilha. O separador é o tab (é o que sai de
     * um Ctrl+C no Excel ou no Google Planilhas) ou o ponto e vírgula —
     * vírgula não serve, porque os títulos das tarefas estão cheios delas.
     *
     * O nº da tarefa da planilha é lido e descartado: a numeração da fila
     * é derivada da ordem, e respeitar um número furado da origem só
     * criaria buracos.
     *
     * @return array<int, array{cycle: int|null, subject: string, name: string}>
     *
     * @throws InvalidTrackImportException
     */
    public function handle(string $content): array
    {
        // A linha não é trimada inteira, só nas pontas invisíveis: uma
        // tarefa sem trilha começa com o separador, e um `trim` comeria a
        // coluna vazia junto, transformando 4 colunas em 3.
        $lines = collect(preg_split('/\R/', $content) ?: [])
            ->map(fn (string $line) => rtrim($line))
            ->filter(fn (string $line) => trim($line) !== '')
            ->values();

        if ($lines->count() > self::MAX_LINES) {
            throw InvalidTrackImportException::tooManyLines(self::MAX_LINES);
        }

        $rows = [];

        foreach ($lines as $index => $line) {
            if ($index === 0 && $this->isHeader($line)) {
                continue;
            }

            $rows[] = $this->parseLine($line, $index + 1);
        }

        if ($rows === []) {
            throw InvalidTrackImportException::empty();
        }

        return $rows;
    }

    /**
     * @return array{cycle: int|null, subject: string, name: string}
     *
     * @throws InvalidTrackImportException
     */
    private function parseLine(string $line, int $number): array
    {
        $columns = array_map(
            fn (string $column) => trim($column),
            explode(str_contains($line, "\t") ? "\t" : ';', $line)
        );

        if (count($columns) === self::SHORT_COLUMNS) {
            [$subject, $name] = $columns;
            $cycle = null;
        } elseif (count($columns) === self::FULL_COLUMNS) {
            [$cycleColumn, , $subject, $name] = $columns;
            $cycle = $this->parseCycle($cycleColumn, $number);
        } else {
            throw InvalidTrackImportException::atLine(
                $number,
                __('são esperadas 4 colunas (trilha, tarefa, disciplina, título) ou 2 (disciplina, título).')
            );
        }

        if ($subject === '' || $name === '') {
            throw InvalidTrackImportException::atLine(
                $number,
                __('disciplina e título não podem ficar em branco.')
            );
        }

        return [
            'cycle' => $cycle,
            'subject' => Str::limit($subject, 255, ''),
            'name' => Str::limit($name, 255, ''),
        ];
    }

    /**
     * @throws InvalidTrackImportException
     */
    private function parseCycle(string $column, int $number): ?int
    {
        if ($column === '') {
            return null;
        }

        if (! ctype_digit($column)) {
            throw InvalidTrackImportException::atLine(
                $number,
                __('a trilha precisa ser um número inteiro.')
            );
        }

        return (int) $column;
    }

    /**
     * O cabeçalho da planilha vem junto na colagem quase sempre. Só a
     * primeira linha é candidata, e ela precisa parecer cabeçalho de
     * verdade — uma tarefa chamada "Disciplina" não é impossível.
     */
    private function isHeader(string $line): bool
    {
        $normalized = Str::lower(Str::ascii($line));

        return str_contains($normalized, 'disciplina')
            && (str_contains($normalized, 'tarefa') || str_contains($normalized, 'titulo'));
    }
}
