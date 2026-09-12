<?php
declare(strict_types=1);

function quoteCheckPath(): string {
    return __DIR__ . '/.data/quote-check.json';
}

function loadQuoteCheck(): ?array {
    $file = quoteCheckPath();
    if (!is_readable($file)) {
        return null;
    }
    $raw = json_decode((string) file_get_contents($file), true);
    if (!is_array($raw) || !isset($raw['lines']) || !is_array($raw['lines'])) {
        return null;
    }
    return $raw;
}

function saveQuoteCheck(array $data): void {
    $dir = dirname(quoteCheckPath());
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('Kon offerte niet opslaan.');
    }
    file_put_contents(quoteCheckPath(), $json);
}

function clearQuoteCheck(): void {
    $file = quoteCheckPath();
    if (is_file($file)) {
        @unlink($file);
    }
}

function quoteParseNumber(mixed $v): ?float {
    if (is_int($v) || is_float($v)) {
        return (float) $v;
    }
    $s = trim(str_replace(['€', '$', "\u{00A0}"], '', (string) $v));
    $s = str_replace(' ', '', $s);
    if ($s === '' || $s === '-') {
        return null;
    }
    if (preg_match('/^-?\d{1,3}(?:\.\d{3})+,\d+$/', $s)) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (preg_match('/^-?\d{1,3}(?:,\d{3})+\.\d+$/', $s)) {
        $s = str_replace(',', '', $s);
    } elseif (str_contains($s, ',') && !str_contains($s, '.')) {
        $s = str_replace(',', '.', $s);
    }
    if (!is_numeric($s)) {
        return null;
    }
    return (float) $s;
}

function quoteArticleBase(string $article): string {
    $s = strtoupper(trim($article));
    $s = str_replace([' ', '.', '_'], '', $s);
    if (preg_match('/(\d{4,8})/', $s, $m)) {
        return $m[1];
    }
    return $s;
}

function quoteSizeKey(string $size): string {
    $s = trim($size);
    if ($s === '') {
        return '';
    }
    $compact = strtoupper(str_replace([' ', '.', '_'], '', $s));
    $compact = str_replace(['–', '—'], '-', $compact);
    $aliases = [
        '2XL' => 'XXL',
        '2X' => 'XXL',
        'XXL' => 'XXL',
        '3XL' => 'XXXL',
        '3X' => 'XXXL',
        'XXXL' => 'XXXL',
        'EENMAAT' => 'één maat',
        'EÉNMAAT' => 'één maat',
        'EéNMAAT' => 'één maat',
        'ONESIZE' => 'één maat',
        'ONSIZE' => 'één maat',
        'OS' => 'één maat',
        'MAATONBEKEND' => 'maat onbekend',
        'ONBEKEND' => 'onbekend',
        'JR' => 'JR',
        'SR' => 'SR',
        'YOUTH' => 'JR',
        'JUNIOR' => 'JR',
        'SENIOR' => 'SR',
        'ADULT' => 'SR',
    ];
    if (isset($aliases[$compact])) {
        return $aliases[$compact];
    }
    $slash = str_replace('-', '/', $compact);
    if (preg_match('/^\d{2,3}(?:\/\d{2,3})?$/', $slash) || preg_match('/^(?:116|128|140|152|164|S|M|L|XL)$/', $compact)) {
        return $slash;
    }
    return $s;
}

function quoteNormText(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, ['é' => 'e', 'ë' => 'e', 'è' => 'e', 'ï' => 'i', 'ü' => 'u', 'ö' => 'o', 'á' => 'a']);
    $s = preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? $s;
    return trim((string) $s);
}

function quotePrintKeyFromName(string $name): ?string {
    $n = quoteNormText($name);
    if ($n === '') {
        return null;
    }
    $map = [
        'rohda' => ['clublogo', 'club logo', 'rohda', 'clubembleem', 'clublogo', 'logo club'],
        'initials' => ['initialen', 'initiaal', 'initials', 'letters'],
        'sponsor' => ['sponsor voorkant', 'logo sponsor voorkant', 'sponsor shirts voorkant', 'bedrijfslogo voor'],
        'sponsor_back' => ['sponsor achterkant', 'logo sponsor achterkant', 'sponsor shirts achterkant', 'bedrijfslogo achter'],
        'sponsor_padded' => ['sponsor padded', 'logo sponsor padded', 'sponsor winterjas'],
        'sponsor_jacket' => ['sponsor regenjas', 'logo sponsor regenjas', 'sponsor field jack', 'sponsor jack achterkant'],
        'sponsor_bag' => ['sponsor tas', 'logo sponsor tas'],
        'name_back' => ['nummer achterop', 'rugnummer', 'nummer shirt'],
        'staff_text' => ['tekst staf', 'staf tekst', 'staff text'],
    ];
    foreach ($map as $key => $needles) {
        foreach ($needles as $needle) {
            if ($n === $needle || str_contains($n, $needle)) {
                return $key;
            }
        }
    }
    if ($n === 'nummer' || $n === 'nummers') {
        return 'name_back';
    }
    return null;
}

function quoteLooksLikeSize(string $v): bool {
    $k = quoteSizeKey($v);
    if ($k === '') {
        return false;
    }
    $known = ['116', '128', '140', '152', '164', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'JR', 'SR', 'één maat', 'maat onbekend', 'onbekend'];
    if (in_array($k, $known, true)) {
        return true;
    }
    return (bool) preg_match('/^\d{2,3}(?:\/\d{2,3})?$/', $k);
}

function quoteHeaderKind(string $cell): string {
    $n = quoteNormText($cell);
    if ($n === '') {
        return '';
    }
    if (in_array($n, ['artikelnummer', 'artikel', 'artikelnr', 'art nr', 'artnr', 'art no', 'art nos', 'sku', 'item no', 'itemnr'], true)
        || str_starts_with($n, 'art') && (str_contains($n, 'nr') || str_contains($n, 'nummer') || $n === 'art')) {
        return 'article';
    }
    if (in_array($n, ['product', 'omschrijving', 'beschrijving', 'naam', 'item', 'type', 'artikelnaam'], true)) {
        return 'name';
    }
    if (in_array($n, ['maat', 'size', 'sz', 'maten'], true)) {
        return 'size';
    }
    if (in_array($n, ['aantal', 'qty', 'quantity', 'stuks', 'stuk', 'aant', 'qty ordered', 'besteld'], true)) {
        return 'qty';
    }
    if (in_array($n, ['prijs', 'stukprijs', 'price', 'eur', 'bedrag', 'tarief'], true) || str_contains($n, 'prijs')) {
        return 'price';
    }
    if (in_array($n, ['kleur', 'color', 'colour'], true)) {
        return 'color';
    }
    if (in_array($n, ['merk', 'brand'], true)) {
        return 'brand';
    }
    if (in_array($n, ['speler', 'naam speler', 'who', 'persoon'], true)) {
        return 'who';
    }
    return '';
}

function quoteSectionKind(string $cell): string {
    $n = quoteNormText($cell);
    if ($n === '') {
        return '';
    }
    if (str_contains($n, 'bestellen')) {
        return 'order';
    }
    if (str_contains($n, 'bedrukken') || str_contains($n, 'per stuk') || str_contains($n, 'per speler') || str_contains($n, 'controle')) {
        return 'skip';
    }
    return '';
}

function quoteIsNoiseRow(array $row): bool {
    $joined = quoteNormText(implode(' ', array_map(static fn($c) => (string) $c, $row)));
    if ($joined === '') {
        return true;
    }
    if (str_contains($joined, 'alle producten') || str_contains($joined, 'totaal bedrukken')) {
        return true;
    }
    if (preg_match('/\b(totaal|subtotal|subtotaal|total)\b/', $joined) && quoteArticleBase($joined) === quoteNormText($joined)) {
        return true;
    }
    return false;
}

function quoteXlsxColIndex(string $ref): int {
    if (!preg_match('/^([A-Z]+)/i', $ref, $m)) {
        return 0;
    }
    $n = 0;
    foreach (str_split(strtoupper($m[1])) as $ch) {
        $n = $n * 26 + (ord($ch) - 64);
    }
    return max(0, $n - 1);
}

function quoteXlsxRowIndex(string $ref): int {
    if (!preg_match('/(\d+)$/', $ref, $m)) {
        return 0;
    }
    return (int) $m[1];
}

function quoteXmlLoad(string $xml): ?SimpleXMLElement {
    $xml = preg_replace('/xmlns(?::[A-Za-z0-9]+)?="[^"]*"/', '', $xml) ?? $xml;
    $sx = @simplexml_load_string($xml);
    return $sx instanceof SimpleXMLElement ? $sx : null;
}

function quoteXmlLocalText(SimpleXMLElement $el, string $local): string {
    $parts = [];
    $walk = static function (SimpleXMLElement $node) use (&$walk, &$parts, $local): void {
        if ($node->getName() === $local) {
            $parts[] = (string) $node;
        }
        foreach ($node->children() as $child) {
            $walk($child);
        }
    };
    $walk($el);
    return implode('', $parts);
}

function quoteXlsxSharedStrings(ZipArchive $zip): array {
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false || $xml === '') {
        return [];
    }
    $sx = quoteXmlLoad($xml);
    if (!$sx) {
        return [];
    }
    $out = [];
    foreach ($sx->xpath('//si') ?: [] as $si) {
        $out[] = quoteXmlLocalText($si, 't');
    }
    return $out;
}

function quoteXlsxSheetNames(ZipArchive $zip): array {
    $xml = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($xml === false) {
        return [];
    }
    $relMap = [];
    if ($rels !== false) {
        $rx = quoteXmlLoad($rels);
        if ($rx) {
            foreach ($rx->Relationship as $rel) {
                $id = (string) $rel['Id'];
                $target = (string) $rel['Target'];
                if ($id !== '' && $target !== '') {
                    $path = 'xl/' . ltrim(str_replace('\\', '/', $target), '/');
                    $path = preg_replace('#^xl/xl/#', 'xl/', $path) ?? $path;
                    $relMap[$id] = $path;
                }
            }
        }
    }
    $wb = quoteXmlLoad($xml);
    if (!$wb) {
        return [];
    }
    $sheets = [];
    foreach ($wb->xpath('//sheet') ?: [] as $sheet) {
        $name = (string) $sheet['name'];
        $rid = (string) ($sheet['id'] ?? '');
        if ($rid === '') {
            foreach ($sheet->attributes() as $attrName => $attrVal) {
                if (strtolower((string) $attrName) === 'id' || str_ends_with(strtolower((string) $attrName), ':id')) {
                    $rid = (string) $attrVal;
                    break;
                }
            }
        }
        $path = $relMap[$rid] ?? '';
        if ($path === '') {
            continue;
        }
        $sheets[] = ['name' => $name !== '' ? $name : basename($path), 'path' => $path];
    }
    if ($sheets === []) {
        $sheets[] = ['name' => 'Blad1', 'path' => 'xl/worksheets/sheet1.xml'];
    }
    return $sheets;
}

function quoteXlsxSheetRows(ZipArchive $zip, string $path, array $shared): array {
    $xml = $zip->getFromName($path);
    if ($xml === false || $xml === '') {
        return [];
    }
    $sx = quoteXmlLoad($xml);
    if (!$sx) {
        return [];
    }
    $rows = [];
    foreach ($sx->xpath('//c') ?: [] as $c) {
        $ref = (string) $c['r'];
        if ($ref === '') {
            continue;
        }
        $r = quoteXlsxRowIndex($ref);
        $col = quoteXlsxColIndex($ref);
        if ($r < 1) {
            continue;
        }
        $type = (string) $c['t'];
        $val = '';
        if ($type === 's') {
            $idx = (int) (string) $c->v;
            $val = (string) ($shared[$idx] ?? '');
        } elseif ($type === 'inlineStr') {
            $val = quoteXmlLocalText($c, 't');
        } elseif ($type === 'b') {
            $val = ((string) $c->v) === '1' ? '1' : '0';
        } else {
            $val = trim((string) $c->v);
        }
        if ($val === '') {
            continue;
        }
        if (!isset($rows[$r])) {
            $rows[$r] = [];
        }
        $rows[$r][$col] = $val;
    }
    ksort($rows);
    $out = [];
    foreach ($rows as $row) {
        if ($row === []) {
            continue;
        }
        $max = max(array_keys($row));
        $line = array_fill(0, $max + 1, '');
        foreach ($row as $i => $v) {
            $line[$i] = $v;
        }
        $out[] = $line;
    }
    return $out;
}

function quoteReadXlsx(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Dit Excel-bestand kan niet worden geopend.');
    }
    $shared = quoteXlsxSharedStrings($zip);
    $sheets = [];
    foreach (quoteXlsxSheetNames($zip) as $meta) {
        $rows = quoteXlsxSheetRows($zip, $meta['path'], $shared);
        if ($rows !== []) {
            $sheets[] = ['name' => $meta['name'], 'rows' => $rows];
        }
    }
    $zip->close();
    if ($sheets === []) {
        throw new RuntimeException('Geen tabel gevonden in het Excel-bestand.');
    }
    return $sheets;
}

function quoteParseCsvText(string $text): array {
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = array_values(array_filter(explode("\n", $text), static fn($l) => trim($l) !== ''));
    if ($lines === []) {
        return [];
    }
    $sample = implode("\n", array_slice($lines, 0, 8));
    $semi = substr_count($sample, ';');
    $comma = substr_count($sample, ',');
    $tab = substr_count($sample, "\t");
    $delim = ',';
    if ($tab >= $semi && $tab >= $comma && $tab > 0) {
        $delim = "\t";
    } elseif ($semi > $comma) {
        $delim = ';';
    }
    $rows = [];
    foreach ($lines as $line) {
        $row = str_getcsv($line, $delim);
        $row = array_map(static fn($c) => trim((string) $c), $row);
        if (implode('', $row) !== '') {
            $rows[] = $row;
        }
    }
    return $rows;
}

function quoteLooseTextRows(string $text): array {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $rows = [];
    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (str_contains($line, "\t")) {
            $rows[] = array_map('trim', explode("\t", $line));
            continue;
        }
        $parts = preg_split('/\s{2,}/', $line) ?: [$line];
        $rows[] = array_map('trim', $parts);
    }
    return $rows;
}

function quoteDetectHeader(array $rows): ?array {
    $best = null;
    $bestScore = 0;
    $limit = min(40, count($rows));
    for ($i = 0; $i < $limit; $i++) {
        $map = [];
        $sizes = [];
        $score = 0;
        foreach ($rows[$i] as $col => $cell) {
            $kind = quoteHeaderKind((string) $cell);
            if ($kind !== '') {
                $map[$kind] = (int) $col;
                $score += $kind === 'article' || $kind === 'qty' || $kind === 'size' ? 3 : 2;
            } elseif (quoteLooksLikeSize((string) $cell)) {
                $sizes[quoteSizeKey((string) $cell)] = (int) $col;
                $score += 1;
            }
        }
        if (isset($map['article']) || isset($map['name'])) {
            $score += 2;
        }
        if ($score > $bestScore && ($map !== [] || count($sizes) >= 3)) {
            $bestScore = $score;
            $best = ['index' => $i, 'map' => $map, 'sizes' => $sizes];
        }
    }
    return $bestScore >= 3 ? $best : null;
}

function quoteSkipSectionTitle(string $cell): bool {
    return quoteSectionKind($cell) === 'skip';
}

/**
 * @return list<array{article:string,name:string,size:string,qty:int,price:?float,print:?string,raw:string}>
 */
function quoteLinesFromRows(array $rows): array {
    $header = quoteDetectHeader($rows);
    $lines = [];
    if ($header === null) {
        foreach ($rows as $row) {
            $line = quoteGuessLooseRow($row);
            if ($line !== null) {
                $lines[] = $line;
            }
        }
        return $lines;
    }

    $start = $header['index'] + 1;
    $map = $header['map'];
    $sizeCols = $header['sizes'];
    $hasOrderSection = false;
    foreach (array_slice($rows, 0, $header['index'] + 1) as $row) {
        if (quoteSectionKind((string) ($row[0] ?? '')) === 'order') {
            $hasOrderSection = true;
        }
    }
    $inSkip = false;
    for ($i = $start; $i < count($rows); $i++) {
        $row = $rows[$i];
        $first = trim((string) ($row[0] ?? ''));
        $sec = quoteSectionKind($first);
        if ($sec === 'skip') {
            $inSkip = true;
            continue;
        }
        if ($sec === 'order') {
            $inSkip = false;
            continue;
        }
        if ($inSkip && $hasOrderSection) {
            continue;
        }
        if (quoteIsNoiseRow($row)) {
            continue;
        }
        if (quoteDetectHeader([$row]) && quoteHeaderKind((string) ($row[0] ?? '')) !== '') {
            continue;
        }

        $article = isset($map['article']) ? trim((string) ($row[$map['article']] ?? '')) : '';
        $name = isset($map['name']) ? trim((string) ($row[$map['name']] ?? '')) : $first;
        $size = isset($map['size']) ? trim((string) ($row[$map['size']] ?? '')) : '';
        $qtyRaw = isset($map['qty']) ? $row[$map['qty']] ?? '' : '';
        $price = isset($map['price']) ? quoteParseNumber($row[$map['price']] ?? '') : null;
        $print = quotePrintKeyFromName($name !== '' ? $name : $first);

        if ($sizeCols !== [] && (!isset($map['size']) || $size === '')) {
            foreach ($sizeCols as $sz => $col) {
                $n = quoteParseNumber($row[$col] ?? '');
                if ($n === null || $n <= 0) {
                    continue;
                }
                $lines[] = [
                    'article' => $article,
                    'name' => $name,
                    'size' => $sz,
                    'qty' => (int) round($n),
                    'price' => $price,
                    'print' => $print,
                    'raw' => trim(implode(' | ', array_filter($row, static fn($c) => trim((string) $c) !== ''))),
                ];
            }
            continue;
        }

        $qty = quoteParseNumber($qtyRaw);
        if (($qty === null || $qty <= 0) && $article !== '' && quoteLooksLikeSize($size)) {
            $qty = 1.0;
        }
        if ($qty === null || $qty <= 0) {
            if ($print !== null && $article === '') {
                continue;
            }
            continue;
        }
        if ($article === '' && $name === '' && $print === null) {
            continue;
        }
        $lines[] = [
            'article' => $article,
            'name' => $name,
            'size' => $size,
            'qty' => (int) round($qty),
            'price' => $price,
            'print' => $print,
            'raw' => trim(implode(' | ', array_filter($row, static fn($c) => trim((string) $c) !== ''))),
        ];
    }
    return $lines;
}

function quoteGuessLooseRow(array $row): ?array {
    $cells = array_values(array_filter(array_map(static fn($c) => trim((string) $c), $row), static fn($c) => $c !== ''));
    if ($cells === [] || quoteIsNoiseRow($cells)) {
        return null;
    }
    $article = '';
    $size = '';
    $qty = null;
    $nameParts = [];
    foreach ($cells as $cell) {
        if ($article === '' && preg_match('/\d{4,8}/', $cell)) {
            $article = $cell;
            continue;
        }
        if ($size === '' && quoteLooksLikeSize($cell) && quoteParseNumber($cell) === null) {
            $size = $cell;
            continue;
        }
        $n = quoteParseNumber($cell);
        if ($n !== null && $n > 0 && $n < 500 && $qty === null && !quoteLooksLikeSize($cell)) {
            $qty = $n;
            continue;
        }
        $nameParts[] = $cell;
    }
    $name = trim(implode(' ', $nameParts));
    $print = quotePrintKeyFromName($name);
    if ($qty === null) {
        return null;
    }
    if ($article === '' && $name === '' && $print === null) {
        return null;
    }
    return [
        'article' => $article,
        'name' => $name,
        'size' => $size,
        'qty' => (int) round($qty),
        'price' => null,
        'print' => $print,
        'raw' => implode(' | ', $cells),
    ];
}

function quoteLinesFromSheets(array $sheets): array {
    $lines = [];
    $warnings = [];
    foreach ($sheets as $sheet) {
        $found = quoteLinesFromRows($sheet['rows'] ?? []);
        if ($found === []) {
            continue;
        }
        foreach ($found as $line) {
            $line['sheet'] = (string) ($sheet['name'] ?? '');
            $lines[] = $line;
        }
    }
    if ($lines === []) {
        $warnings[] = 'Geen bestelregels herkend. Controleer of er kolommen zijn voor artikel, maat en aantal.';
    }
    return ['lines' => $lines, 'warnings' => $warnings];
}

function parseQuoteBytes(string $bytes, string $filename): array {
    $filename = trim($filename);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $tmp = tempnam(sys_get_temp_dir(), 'krq');
    if ($tmp === false) {
        throw new RuntimeException('Tijdelijk bestand mislukt.');
    }
    file_put_contents($tmp, $bytes);
    try {
        if (str_starts_with($bytes, 'PK') && in_array($ext, ['xlsx', 'xlsm', 'xls', ''], true)) {
            $sheets = quoteReadXlsx($tmp);
            $parsed = quoteLinesFromSheets($sheets);
            $parsed['format'] = 'xlsx';
            $parsed['sheets'] = array_column($sheets, 'name');
            return $parsed;
        }
        if ($ext === 'xls') {
            throw new RuntimeException('Oud Excel-formaat (.xls). Sla de offerte op als .xlsx of CSV.');
        }
        $text = $bytes;
        if (!mb_check_encoding($text, 'UTF-8')) {
            $conv = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252,ISO-8859-1');
            $text = is_string($conv) ? $conv : $text;
        }
        if ($ext === 'pdf' || str_starts_with($bytes, '%PDF')) {
            $text = quotePdfToText($bytes);
            if (trim($text) === '') {
                throw new RuntimeException('Deze PDF is geen tekstbestand. Sla hem op als Excel/CSV of plak de tabel.');
            }
        }
        $rows = quoteParseCsvText($text);
        if (count($rows) > 0 && max(array_map('count', $rows)) < 2) {
            $loose = quoteLooseTextRows($text);
            if (count($loose) >= count($rows)) {
                $rows = $loose;
            }
        }
        $parsed = quoteLinesFromSheets([['name' => $filename !== '' ? $filename : 'tekst', 'rows' => $rows]]);
        $parsed['format'] = $ext === 'pdf' ? 'pdf' : 'text';
        $parsed['sheets'] = [$parsed['format']];
        return $parsed;
    } finally {
        @unlink($tmp);
    }
}

function quotePdfToText(string $bytes): string {
    $out = [];
    if (preg_match_all('/\((?:\\\\.|[^\\\\)]){1,200}\)\s*Tj/s', $bytes, $m)) {
        foreach ($m[0] as $chunk) {
            if (preg_match('/^\((.*)\)\s*Tj$/s', $chunk, $mm)) {
                $t = str_replace(['\\(', '\\)', '\\\\', '\\n'], ['(', ')', '\\', ' '], $mm[1]);
                $out[] = $t;
            }
        }
    }
    if ($out !== []) {
        return implode("\n", $out);
    }
    $plain = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\xFF]+/', "\n", $bytes) ?? '';
    return $plain;
}

function parseQuoteUpload(?array $file, string $pasted): array {
    $pasted = trim($pasted);
    if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload mislukt (code ' . $err . ').');
        }
        $name = (string) ($file['name'] ?? 'offerte');
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Geen geldig bestand.');
        }
        if ($size < 1 || $size > 8 * 1024 * 1024) {
            throw new RuntimeException('Bestand is leeg of groter dan 8 MB.');
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xlsm', 'xls', 'csv', 'txt', 'pdf', 'tsv'], true)) {
            throw new RuntimeException('Gebruik Excel (.xlsx), CSV, PDF of plak de tabel.');
        }
        $bytes = (string) file_get_contents($tmp);
        $parsed = parseQuoteBytes($bytes, $name);
        $parsed['file'] = $name;
        return $parsed;
    }
    if ($pasted !== '') {
        if (strlen($pasted) > 800_000) {
            throw new RuntimeException('Geplakte tekst is te lang.');
        }
        $parsed = parseQuoteBytes($pasted, 'geplakt.csv');
        $parsed['file'] = 'geplakte tabel';
        return $parsed;
    }
    throw new RuntimeException('Kies een offertebestand of plak de tabel.');
}

function expectedQuoteGarments(array $shopByType): array {
    $groups = [];
    foreach ($shopByType as $shop) {
        $article = trim((string) ($shop['article'] ?? ''));
        $base = quoteArticleBase($article);
        $label = (string) ($shop['label'] ?? '');
        foreach (($shop['sizes'] ?? []) as $sz => $cnt) {
            $cnt = (int) $cnt;
            if ($cnt < 1) {
                continue;
            }
            $size = (string) $sz;
            $sk = quoteSizeKey($size);
            $key = ($base !== '' ? 'a:' . $base : 'n:' . quoteNormText($label)) . '|' . $sk;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'kind' => 'garment',
                    'article' => $article,
                    'article_base' => $base,
                    'names' => [],
                    'size' => $size,
                    'size_key' => $sk,
                    'qty' => 0,
                    'tids' => [],
                ];
            }
            $groups[$key]['qty'] += $cnt;
            if ($label !== '') {
                $groups[$key]['names'][$label] = true;
            }
            $groups[$key]['tids'][] = (int) ($shop['tid'] ?? 0);
        }
    }
    return $groups;
}

function expectedQuotePrints(array $printRows): array {
    $groups = [];
    foreach ($printRows as $key => $row) {
        $qty = (int) ($row['count'] ?? 0);
        if ($qty < 1) {
            continue;
        }
        $groups['p:' . $key] = [
            'key' => 'p:' . $key,
            'kind' => 'print',
            'print' => (string) $key,
            'article' => '',
            'article_base' => '',
            'names' => [(string) ($row['label'] ?? $key) => true],
            'size' => '',
            'size_key' => '',
            'qty' => $qty,
            'tids' => [],
        ];
    }
    return $groups;
}

function compareQuoteToOrder(array $shopByType, array $printRows, array $quoteLines): array {
    $quoteHasPrint = false;
    foreach ($quoteLines as $line) {
        $print = $line['print'] ?? null;
        if ((!is_string($print) || $print === '') && trim((string) ($line['name'] ?? '')) !== '') {
            $print = quotePrintKeyFromName((string) $line['name']);
        }
        if (is_string($print) && $print !== '') {
            $quoteHasPrint = true;
            break;
        }
    }
    $expected = expectedQuoteGarments($shopByType);
    if ($quoteHasPrint) {
        $expected += expectedQuotePrints($printRows);
    }
    $quoteQty = [];
    $quoteMeta = [];
    $unknown = [];
    $quotePieces = 0;

    foreach ($quoteLines as $line) {
        $qty = (int) ($line['qty'] ?? 0);
        if ($qty < 1) {
            continue;
        }
        $quotePieces += $qty;
        $print = $line['print'] ?? null;
        $name = trim((string) ($line['name'] ?? ''));
        if (!is_string($print) || $print === '') {
            $print = quotePrintKeyFromName($name);
        }
        $article = trim((string) ($line['article'] ?? ''));
        $base = quoteArticleBase($article);
        $sizeKey = quoteSizeKey((string) ($line['size'] ?? ''));

        $key = null;
        if (is_string($print) && $print !== '' && ($base === '' || $article === '')) {
            $key = 'p:' . $print;
        } elseif ($base !== '') {
            $key = 'a:' . $base . '|' . $sizeKey;
            if (!isset($expected[$key]) && $sizeKey === '') {
                $cands = [];
                foreach (array_keys($expected) as $ek) {
                    if (str_starts_with((string) $ek, 'a:' . $base . '|')) {
                        $cands[] = $ek;
                    }
                }
                if (count($cands) === 1) {
                    $key = $cands[0];
                }
            }
        } else {
            $nameKey = quoteNormText($name);
            foreach ($expected as $ek => $ex) {
                if (($ex['kind'] ?? '') !== 'garment') {
                    continue;
                }
                foreach (array_keys($ex['names']) as $lab) {
                    if ($nameKey !== '' && (quoteNormText((string) $lab) === $nameKey || str_contains(quoteNormText((string) $lab), $nameKey) || str_contains($nameKey, quoteNormText((string) $lab)))) {
                        if ($sizeKey === '' || $sizeKey === ($ex['size_key'] ?? '')) {
                            $key = $ek;
                            break 2;
                        }
                    }
                }
            }
            if ($key === null && is_string($print) && $print !== '') {
                $key = 'p:' . $print;
            }
        }

        if ($key === null || (!isset($expected[$key]) && !str_starts_with((string) $key, 'p:'))) {
            $unknown[] = [
                'name' => $name !== '' ? $name : ($article !== '' ? $article : 'Onbekende regel'),
                'article' => $article,
                'size' => (string) ($line['size'] ?? ''),
                'qty' => $qty,
                'raw' => (string) ($line['raw'] ?? ''),
            ];
            continue;
        }
        if (!isset($quoteQty[$key])) {
            $quoteQty[$key] = 0;
            $quoteMeta[$key] = [
                'article' => $article,
                'name' => $name,
                'price' => $line['price'] ?? null,
            ];
        }
        $quoteQty[$key] += $qty;
        if ($article !== '' && ($quoteMeta[$key]['article'] ?? '') === '') {
            $quoteMeta[$key]['article'] = $article;
        }
        if ($name !== '' && ($quoteMeta[$key]['name'] ?? '') === '') {
            $quoteMeta[$key]['name'] = $name;
        }
    }

    $rows = [];
    $ok = $short = $extra = $missing = 0;
    $appPieces = 0;

    foreach ($expected as $key => $ex) {
        $need = (int) $ex['qty'];
        $got = (int) ($quoteQty[$key] ?? 0);
        unset($quoteQty[$key]);
        $appPieces += $need;
        $diff = $got - $need;
        if ($got === 0) {
            $status = 'missing';
            $missing++;
        } elseif ($got < $need) {
            $status = 'short';
            $short++;
        } elseif ($got > $need) {
            $status = 'over';
            $extra++;
        } else {
            $status = 'ok';
            $ok++;
        }
        $rows[] = [
            'status' => $status,
            'kind' => $ex['kind'],
            'name' => implode(' + ', array_keys($ex['names'])),
            'article' => $ex['article'],
            'size' => $ex['size'],
            'app' => $need,
            'quote' => $got,
            'diff' => $diff,
        ];
    }

    foreach ($quoteQty as $key => $got) {
        if ($got < 1) {
            continue;
        }
        $meta = $quoteMeta[$key] ?? [];
        $unknown[] = [
            'name' => (string) ($meta['name'] ?? $key),
            'article' => (string) ($meta['article'] ?? ''),
            'size' => str_contains((string) $key, '|') ? explode('|', (string) $key, 2)[1] : '',
            'qty' => $got,
            'raw' => '',
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        $rank = ['missing' => 0, 'short' => 1, 'over' => 2, 'ok' => 3];
        return [($rank[$a['status']] ?? 9), $a['name'], $a['size']] <=> [($rank[$b['status']] ?? 9), $b['name'], $b['size']];
    });

    $problems = $missing + $short;
    return [
        'ok' => $problems === 0 && $unknown === [],
        'complete' => $problems === 0,
        'counts' => [
            'ok' => $ok,
            'short' => $short,
            'over' => $extra,
            'missing' => $missing,
            'unknown' => count($unknown),
            'app_pieces' => $appPieces,
            'quote_pieces' => $quotePieces,
        ],
        'rows' => $rows,
        'unknown' => $unknown,
    ];
}

function quoteCheckPayload(array $parsed, array $shopByType, array $printRows): array {
    $report = compareQuoteToOrder($shopByType, $printRows, $parsed['lines'] ?? []);
    $stamp = new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam'));
    return [
        'file' => (string) ($parsed['file'] ?? 'offerte'),
        'at' => $stamp->getTimestamp(),
        'when' => $stamp->format('d-m-Y H:i'),
        'format' => (string) ($parsed['format'] ?? ''),
        'sheets' => $parsed['sheets'] ?? [],
        'warnings' => $parsed['warnings'] ?? [],
        'lines' => array_values($parsed['lines'] ?? []),
        'report' => $report,
    ];
}
