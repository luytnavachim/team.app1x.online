<?php
declare(strict_types=1);

function quoteLetterProductName(string $base, string $fallback, string $brand): string {
    $map = [
        '410014' => 'Stanno Bolt T-shirt',
        '420000' => 'Stanno Field short',
        '420004' => 'Stanno Field short',
        '444007' => 'Stanno Raw Crew Sock',
        '415007' => 'Stanno LS GK Set',
        '463003' => 'Poloshirt Stanno Field',
        '454002' => 'Stanno Field Jack',
        '456004' => 'Stanno Prime Padded',
        '484837' => 'Stanno Pro Bag Prime',
        '484838' => 'Stanno Pro Bag Prime',
        '444004' => 'Footless Sock Stanno',
        '440001' => 'Stanno Uni II Sock',
        '440125' => 'Stanno Uni Pro Sock',
        '425105' => 'Stanno Bounce Goalkeeper Pants',
        '408038' => 'Stanno Bolt Quarter Zip Top',
    ];
    if ($base !== '' && isset($map[$base])) {
        return $map[$base];
    }
    $name = trim($fallback);
    $brand = trim($brand);
    if ($brand !== '' && $name !== '' && !str_contains(mb_strtolower($name, 'UTF-8'), mb_strtolower($brand, 'UTF-8'))) {
        return $brand . ' ' . $name;
    }
    return $name !== '' ? $name : ($brand !== '' ? $brand : 'Artikel');
}

function quoteLetterPrintLabel(string $key, string $fallback = ''): string {
    return match ($key) {
        'rohda' => 'Logo Rohda Raalte op linkerborst bovenkleding en tas',
        'initials' => 'initialen op kleding en tassen',
        'sponsor_bag' => 'Bedrukkingen in 1 kleur op de sporttas',
        'name_back' => 'Nummers achterzijde shirts spelers',
        'staff_text' => 'Teksten: Trainer en Staf',
        'sponsor' => 'Bedrukkingen sponsoren op shirts voorzijde',
        'sponsor_back' => 'Bedrukkingen sponsoren op shirts achterzijde',
        'sponsor_padded' => 'Bedrukkingen op rechterborst padded jacks',
        'sponsor_jacket' => 'Bedrukkingen op achterzijde Field jack',
        default => $fallback !== '' ? $fallback : $key,
    };
}

function quoteLetterSizePair(int $qty, string $size): string {
    if ($size === 'One SIZE') {
        return $size;
    }
    if ($qty === 1 && str_contains($size, '/')) {
        return $size;
    }
    return $qty . '/' . $size;
}

function quoteLetterSizeLabel(string $size): string {
    $k = quoteSizeKey($size);
    if ($k === 'één maat') {
        return 'One SIZE';
    }
    $socks = [
        '25/29' => '25-29',
        '30/35' => '30-35',
        '31/35' => '31-35',
        '36/40' => '36-40',
        '41/44' => '41-44',
        '45/48' => '45/48',
        '45/47' => '45/48',
    ];
    if (isset($socks[$k])) {
        return $socks[$k];
    }
    return $size !== '' ? $size : $k;
}

function quoteLetterSizeBand(string $size): string {
    $k = quoteSizeKey($size);
    if (in_array($k, ['116', '128', '140', '152', '164', 'JR'], true)) {
        return 'Junior';
    }
    if (in_array($k, ['S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'SR'], true)) {
        return 'Senior';
    }
    return 'Maten';
}

function quoteLetterEuro(?float $n): string {
    if ($n === null) {
        return '';
    }
    return '€ ' . number_format($n, 2, ',', '');
}

function quoteLetterNlDate(DateTimeInterface $stamp): string {
    $months = [
        1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april',
        5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'augustus',
        9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december',
    ];
    return (int) $stamp->format('j') . ' ' . $months[(int) $stamp->format('n')] . ' ' . $stamp->format('Y');
}

function quoteLetterContact(array $quoteStored): array {
    $file = (string) ($quoteStored['file'] ?? '');
    $full = 'Micha van Tuyl';
    if (preg_match('/-\s*([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\' -]+)\.pdf$/iu', $file, $m)) {
        $full = trim($m[1]);
    }
    $first = trim(explode(' ', $full)[0] ?? 'Micha');
    return ['full' => $full, 'first' => $first !== '' ? $first : 'Micha'];
}

function quoteLetterPriceNear(?float $a, ?float $b): bool {
    if ($a === null || $b === null) {
        return true;
    }
    return abs($a - $b) < 0.015;
}

/**
 * @return array{garments: array<string, array<string, array{qty:int,article:string,name:string,price:?float}>>, prints: array<string, array{qty:int,name:string,price:?float,raw:string}>}
 */
function quoteLetterIndex(array $lines): array {
    $garments = [];
    $prints = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $qty = (int) ($line['qty'] ?? 0);
        if ($qty < 1) {
            continue;
        }
        $name = trim((string) ($line['name'] ?? ''));
        $print = $line['print'] ?? null;
        if (!is_string($print) || $print === '') {
            $print = quotePrintKeyFromName($name);
        }
        $article = trim((string) ($line['article'] ?? ''));
        $base = quoteArticleBase($article);
        $price = isset($line['price']) && $line['price'] !== null && $line['price'] !== ''
            ? quoteParseNumber($line['price'])
            : null;
        if (is_string($print) && $print !== '' && ($base === '' || $article === '')) {
            if (!isset($prints[$print])) {
                $prints[$print] = ['qty' => 0, 'name' => $name, 'price' => $price, 'raw' => (string) ($line['raw'] ?? $name)];
            }
            $prints[$print]['qty'] += $qty;
            if ($price !== null) {
                $prints[$print]['price'] = $price;
            }
            continue;
        }
        if ($base === '') {
            continue;
        }
        $sk = quoteSizeKey((string) ($line['size'] ?? ''));
        foreach (array_unique(array_filter([$base, ...quoteArticleAliases($base)])) as $alias) {
            if (!isset($garments[$alias])) {
                $garments[$alias] = [];
            }
        }
        if (!isset($garments[$base][$sk])) {
            $garments[$base][$sk] = [
                'qty' => 0,
                'article' => $article,
                'name' => $name,
                'price' => $price,
            ];
        }
        $garments[$base][$sk]['qty'] += $qty;
        if ($price !== null) {
            $garments[$base][$sk]['price'] = $price;
        }
        if ($article !== '') {
            $garments[$base][$sk]['article'] = $article;
        }
    }
    return ['garments' => $garments, 'prints' => $prints];
}

function quoteLetterLookupGarment(array $index, string $base, string $sizeKey): ?array {
    foreach (array_unique(array_filter([$base, ...quoteArticleAliases($base)])) as $alias) {
        if (isset($index['garments'][$alias][$sizeKey])) {
            return $index['garments'][$alias][$sizeKey];
        }
    }
    return null;
}

function quoteLetterQuoteArticle(array $index, string $base): string {
    foreach (array_unique(array_filter([$base, ...quoteArticleAliases($base)])) as $alias) {
        foreach ($index['garments'][$alias] ?? [] as $row) {
            $art = trim((string) ($row['article'] ?? ''));
            if ($art !== '') {
                return $art;
            }
        }
    }
    return '';
}

/**
 * @param array<int, array<string, mixed>> $shopByType
 * @param array<string, array<string, mixed>> $printRows
 */
function quoteLetterModel(array $shopByType, array $printRows, array $types, ?array $quoteStored, ?array $quoteReport): array {
    $stamp = new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam'));
    $stored = is_array($quoteStored) ? $quoteStored : [];
    $index = quoteLetterIndex($stored['lines'] ?? []);
    $hasQuote = ($stored['lines'] ?? []) !== [];
    $contact = quoteLetterContact($stored);

    $merged = [];
    foreach ($shopByType as $shop) {
        $tid = (int) ($shop['tid'] ?? 0);
        $t = $types[$tid] ?? [];
        $article = trim((string) ($shop['article'] ?? ($t['article_number'] ?? '')));
        $base = quoteArticleBase($article);
        $key = $base !== '' ? 'a:' . $base : 't:' . $tid;
        if (!isset($merged[$key])) {
            $merged[$key] = [
                'tid' => $tid,
                'article' => $article,
                'base' => $base,
                'label' => (string) ($shop['label'] ?? shortTypeName($tid, $types)),
                'brand' => (string) ($shop['brand'] ?? ($t['brand'] ?? 'Stanno')),
                'color' => (string) ($shop['color'] ?? ($t['color'] ?? '')),
                'sizes' => [],
                'type' => $t,
            ];
        }
        foreach (($shop['sizes'] ?? []) as $sz => $cnt) {
            $cnt = (int) $cnt;
            if ($cnt < 1) {
                continue;
            }
            $sz = (string) $sz;
            $merged[$key]['sizes'][$sz] = ($merged[$key]['sizes'][$sz] ?? 0) + $cnt;
        }
        if ($merged[$key]['article'] === '' && $article !== '') {
            $merged[$key]['article'] = $article;
        }
    }

    $blocks = [];
    $usedQuoteSizes = [];
    $clothingTotal = 0.0;
    $quoteClothingTotal = 0.0;
    $diffCount = 0;
    $mark = static function (bool $on) use (&$diffCount): bool {
        if ($on) {
            $diffCount++;
        }
        return $on;
    };

    foreach ($merged as $item) {
        $base = (string) $item['base'];
        $article = (string) $item['article'];
        $t = $item['type'];
        $name = quoteLetterProductName($base, (string) $item['label'], (string) $item['brand']);
        $color = mb_strtolower(trim((string) $item['color']), 'UTF-8');
        $quoteArt = $base !== '' ? quoteLetterQuoteArticle($index, $base) : '';
        $skuDiff = $quoteArt !== '' && (
            quoteArticleIsMalformed($quoteArt)
            || (quoteArticleBase($quoteArt) !== $base && quoteArticleBase($quoteArt) !== quoteArticleBase($article))
        );
        $displayArt = $article;
        if ($quoteArt !== '' && !quoteArticleIsMalformed($quoteArt) && quoteArticleBase($quoteArt) === $base) {
            $displayArt = $quoteArt;
        }

        $appQty = (int) array_sum($item['sizes']);
        $quoteQty = 0;
        $sizeRows = [];
        $bandPrices = ['Junior' => null, 'Senior' => null, 'Maten' => null];
        $quoteBandPrices = ['Junior' => null, 'Senior' => null, 'Maten' => null];

        foreach ($item['sizes'] as $sz => $cnt) {
            $sk = quoteSizeKey((string) $sz);
            $band = quoteLetterSizeBand((string) $sz);
            $unit = priceFor($t, (string) $sz);
            $q = $base !== '' ? quoteLetterLookupGarment($index, $base, $sk) : null;
            if ($q !== null) {
                $usedQuoteSizes[$base . '|' . $sk] = true;
                foreach (quoteArticleAliases($base) as $alias) {
                    $usedQuoteSizes[$alias . '|' . $sk] = true;
                }
            }
            $got = (int) ($q['qty'] ?? 0);
            $quoteQty += $got;
            $qPrice = isset($q['price']) ? quoteParseNumber($q['price']) : null;
            if ($unit !== null) {
                $clothingTotal += $unit * (int) $cnt;
                if ($bandPrices[$band] === null) {
                    $bandPrices[$band] = $unit;
                }
            }
            if ($qPrice !== null) {
                $quoteClothingTotal += $qPrice * $got;
                if ($quoteBandPrices[$band] === null) {
                    $quoteBandPrices[$band] = $qPrice;
                }
            }
            $sizeDiff = $hasQuote && $got !== (int) $cnt;
            $priceDiff = $hasQuote && $q !== null && !quoteLetterPriceNear($unit, $qPrice);
            $sizeRows[] = [
                'band' => $band,
                'size' => quoteLetterSizeLabel((string) $sz),
                'qty' => (int) $cnt,
                'quote_qty' => $got,
                'price' => $unit,
                'diff' => $mark($sizeDiff || $priceDiff || ($hasQuote && $q === null)),
            ];
        }

        if ($base !== '') {
            foreach (array_unique(array_filter([$base, ...quoteArticleAliases($base)])) as $alias) {
                foreach ($index['garments'][$alias] ?? [] as $sk => $qrow) {
                    $ukey = $alias . '|' . $sk;
                    if (!empty($usedQuoteSizes[$ukey])) {
                        continue;
                    }
                    $usedQuoteSizes[$ukey] = true;
                    $usedQuoteSizes[$base . '|' . $sk] = true;
                    $got = (int) ($qrow['qty'] ?? 0);
                    if ($got < 1) {
                        continue;
                    }
                    $quoteQty += $got;
                    $band = $sk === '' ? 'Maten' : quoteLetterSizeBand($sk);
                    $qPrice = isset($qrow['price']) ? quoteParseNumber($qrow['price']) : null;
                    if ($qPrice !== null) {
                        $quoteClothingTotal += $qPrice * $got;
                    }
                    $sizeRows[] = [
                        'band' => $band,
                        'size' => quoteLetterSizeLabel($sk),
                        'qty' => 0,
                        'quote_qty' => $got,
                        'price' => $qPrice,
                        'diff' => $mark(true),
                        'only_quote' => true,
                    ];
                }
            }
        }

        $bandsUsed = [];
        foreach ($sizeRows as $row) {
            $bandsUsed[$row['band']] = true;
        }
        $juniorUnit = $bandPrices['Junior'];
        $seniorUnit = $bandPrices['Senior'];
        $splitNamed = !empty($bandsUsed['Junior']) && !empty($bandsUsed['Senior'])
            && $juniorUnit !== null && $seniorUnit !== null
            && !quoteLetterPriceNear($juniorUnit, $seniorUnit);

        $bands = [];
        $bandNames = $splitNamed ? ['Junior', 'Senior', 'Maten'] : ['Maten'];
        foreach ($bandNames as $bandName) {
            $parts = [];
            foreach ($sizeRows as $row) {
                $effective = $splitNamed ? (string) $row['band'] : 'Maten';
                if ($effective === $bandName) {
                    $parts[] = $row;
                }
            }
            if ($parts === []) {
                continue;
            }
            $price = $bandPrices[$bandName] ?? $bandPrices['Maten'] ?? $parts[0]['price'] ?? null;
            $qPrice = $quoteBandPrices[$bandName] ?? $quoteBandPrices['Maten'] ?? null;
            $bands[] = [
                'label' => $bandName,
                'parts' => $parts,
                'price' => $price,
                'price_diff' => $mark($hasQuote && $qPrice !== null && !quoteLetterPriceNear($price, $qPrice)),
            ];
        }

        $qtyDiff = $hasQuote && $quoteQty !== $appQty;
        $missing = $hasQuote && $quoteQty === 0;
        $blocks[] = [
            'qty' => $appQty,
            'quote_qty' => $quoteQty,
            'name' => $name,
            'article' => $displayArt,
            'quote_article' => $skuDiff ? $quoteArt : '',
            'color' => $color,
            'qty_diff' => $mark($qtyDiff || $missing),
            'sku_diff' => $mark($hasQuote && $skuDiff),
            'bands' => $bands,
            'base' => $base,
        ];
    }

    $articleRank = ['410014' => 0, '420000' => 1, '420004' => 2, '444007' => 3, '415007' => 4, '463003' => 5, '454002' => 6, '456004' => 7, '484837' => 8, '484838' => 8, '444004' => 9];
    usort($blocks, static function (array $a, array $b) use ($articleRank): int {
        $ra = $articleRank[$a['base'] ?? ''] ?? 50;
        $rb = $articleRank[$b['base'] ?? ''] ?? 50;
        return [$ra, (string) $a['name']] <=> [$rb, (string) $b['name']];
    });

    $printOrder = ['rohda', 'initials', 'sponsor_bag', 'name_back', 'staff_text', 'sponsor', 'sponsor_back', 'sponsor_padded', 'sponsor_jacket'];
    $prints = [];
    $printTotal = 0.0;
    $quotePrintTotal = 0.0;
    $usedPrints = [];
    foreach ($printOrder as $key) {
        $row = $printRows[$key] ?? null;
        $appN = (int) ($row['count'] ?? 0);
        $unit = isset($row['unit']) ? quoteParseNumber($row['unit']) : null;
        $q = $index['prints'][$key] ?? null;
        $got = (int) ($q['qty'] ?? 0);
        if ($appN < 1 && $got < 1) {
            continue;
        }
        $usedPrints[$key] = true;
        if ($unit !== null) {
            $printTotal += $unit * $appN;
        }
        $qPrice = isset($q['price']) ? quoteParseNumber($q['price']) : null;
        if ($qPrice !== null) {
            $quotePrintTotal += $qPrice * $got;
        }
        $qtyDiff = $hasQuote && $appN !== $got;
        $priceDiff = $hasQuote && $q !== null && !quoteLetterPriceNear($unit, $qPrice);
        $prints[] = [
            'qty' => $appN,
            'quote_qty' => $got,
            'name' => quoteLetterPrintLabel($key, (string) ($row['label'] ?? '')),
            'price' => $unit,
            'only_quote' => $appN < 1 && $got > 0,
            'qty_diff' => $qtyDiff,
            'price_diff' => $priceDiff,
            'diff' => $mark($qtyDiff || $priceDiff),
        ];
    }
    foreach ($index['prints'] as $key => $q) {
        if (!empty($usedPrints[$key])) {
            continue;
        }
        $got = (int) ($q['qty'] ?? 0);
        if ($got < 1) {
            continue;
        }
        $qPrice = isset($q['price']) ? quoteParseNumber($q['price']) : null;
        if ($qPrice !== null) {
            $quotePrintTotal += $qPrice * $got;
        }
        $prints[] = [
            'qty' => 0,
            'quote_qty' => $got,
            'name' => quoteLetterPrintLabel((string) $key, (string) ($q['name'] ?? $key)),
            'price' => $qPrice,
            'only_quote' => true,
            'qty_diff' => true,
            'price_diff' => false,
            'diff' => $mark(true),
        ];
    }

    $extra = [];
    foreach ($quoteReport['unknown'] ?? [] as $u) {
        if (!is_array($u)) {
            continue;
        }
        $extra[] = [
            'name' => (string) ($u['name'] ?? ''),
            'article' => (string) ($u['article'] ?? ''),
            'size' => quoteLetterSizeLabel((string) ($u['size'] ?? '')),
            'qty' => (int) ($u['qty'] ?? 0),
        ];
    }

    $clothingDiff = $hasQuote && !quoteLetterPriceNear($clothingTotal, $quoteClothingTotal) && $quoteClothingTotal > 0;
    $printDiff = $hasQuote && !quoteLetterPriceNear($printTotal, $quotePrintTotal) && $quotePrintTotal > 0;
    if ($clothingDiff) {
        $diffCount++;
    }
    if ($printDiff) {
        $diffCount++;
    }

    return [
        'team' => 'Rohda Raalte 14-2',
        'contact' => $contact['full'],
        'first' => $contact['first'],
        'sponsor' => 'Diversen',
        'place' => 'Raalte',
        'date' => quoteLetterNlDate($stamp),
        'when' => $stamp->format('d-m-Y-H.i'),
        'has_quote' => $hasQuote,
        'quote_file' => (string) ($stored['file'] ?? ''),
        'quote_when' => (string) ($stored['when'] ?? ''),
        'blocks' => $blocks,
        'prints' => $prints,
        'extra' => $extra,
        'clothing_total' => $clothingTotal,
        'print_total' => $printTotal,
        'quote_clothing_total' => $quoteClothingTotal,
        'quote_print_total' => $quotePrintTotal,
        'clothing_diff' => $clothingDiff,
        'print_diff' => $printDiff,
        'diff_count' => $diffCount + count($extra),
    ];
}

function quoteLetterMark(string $text, bool $diff): string {
    $html = h($text);
    return $diff ? '<span class="diff">' . $html . '</span>' : $html;
}

function quoteLetterHtml(array $letter): string {
    $out = '';
    $out .= '<p class="head">' . h($letter['team']) . '<br>' . h($letter['contact']) . '<br>Sponsor: ' . h($letter['sponsor']) . '</p>';
    $out .= '<p class="head">' . h($letter['place']) . ', ' . h($letter['date']) . '</p>';
    $out .= '<p>Beste ' . h($letter['first']) . ',</p>';
    $out .= '<p>Hartelijk dank voor jullie offerteaanvraag. Hierbij bied ik geheel vrijblijvend onderstaande goederen aan.</p>';

    foreach ($letter['blocks'] as $block) {
        $title = quoteLetterMark((string) $block['qty'], !empty($block['qty_diff']))
            . ' ' . h((string) $block['name']);
        $art = trim((string) $block['article']);
        if ($art !== '') {
            $title .= ', ' . h($art);
            if (!empty($block['quote_article'])) {
                $title .= ' ' . quoteLetterMark('(offerte ' . (string) $block['quote_article'] . ')', true);
            }
        }
        $color = trim((string) $block['color']);
        if ($color !== '') {
            $title .= ', kleur: ' . h($color);
        }
        $out .= '<p class="item">' . $title . '<br>';
        foreach ($block['bands'] as $band) {
            $bits = [];
            foreach ($band['parts'] as $part) {
                $showQty = !empty($part['only_quote']) ? (int) $part['quote_qty'] : (int) $part['qty'];
                $piece = quoteLetterSizePair($showQty, (string) $part['size']);
                if (!empty($part['only_quote'])) {
                    $piece .= ' (niet in Kitroom)';
                } elseif (!empty($part['diff']) && (int) $part['quote_qty'] !== (int) $part['qty']) {
                    $piece .= ' (offerte ' . (int) $part['quote_qty'] . ')';
                }
                $bits[] = quoteLetterMark($piece, !empty($part['diff']));
            }
            $line = h((string) $band['label']) . ': ' . implode(', ', $bits);
            if (($band['price'] ?? null) !== null) {
                $line .= ' ' . quoteLetterMark(quoteLetterEuro((float) $band['price']), !empty($band['price_diff']));
            }
            $out .= $line . '<br>';
        }
        $out .= '</p>';
    }

    foreach ($letter['prints'] as $p) {
        $appN = (int) $p['qty'];
        $got = (int) $p['quote_qty'];
        $showQty = !empty($p['only_quote']) ? $got : $appN;
        $line = quoteLetterMark((string) $showQty, !empty($p['qty_diff']) || !empty($p['only_quote']))
            . ' ' . quoteLetterMark((string) $p['name'], !empty($p['only_quote']));
        if (($p['price'] ?? null) !== null) {
            $line .= ' ' . quoteLetterMark(quoteLetterEuro((float) $p['price']), !empty($p['price_diff']));
        }
        if (!empty($p['only_quote'])) {
            $line .= ' ' . quoteLetterMark('(niet in Kitroom)', true);
        } elseif (!empty($p['diff']) && $appN !== $got) {
            $line .= ' ' . quoteLetterMark('(offerte ' . $got . ')', true);
        }
        $out .= '<p class="item">' . $line . '</p>';
    }

    if (!empty($letter['extra'])) {
        $out .= '<p class="item">' . quoteLetterMark('Staat op de offerte, niet in Kitroom:', true) . '<br>';
        foreach ($letter['extra'] as $ex) {
            $bits = [(string) (int) $ex['qty'], trim((string) $ex['name'])];
            if (trim((string) $ex['article']) !== '') {
                $bits[] = (string) $ex['article'];
            }
            if (trim((string) $ex['size']) !== '') {
                $bits[] = (string) $ex['size'];
            }
            $out .= quoteLetterMark(trim(implode(' ', array_filter($bits))), true) . '<br>';
        }
        $out .= '</p>';
    }

    $out .= '<p class="item">'
        . quoteLetterMark('Totaalprijs kleding en tassen ' . quoteLetterEuro((float) $letter['clothing_total']), !empty($letter['clothing_diff']))
        . '<br>'
        . quoteLetterMark('Drukwerk divers ' . quoteLetterEuro((float) $letter['print_total']), !empty($letter['print_diff']))
        . '</p>';
    $out .= '<p>Prijzen: per stuk, netto, exclusief btw.<br>Betaling: binnen 14 dagen na levering.<br>Levertijd: ca. 3 weken.</p>';
    $out .= '<p>Ik vertrouw hiermee een interessante aanbieding te hebben gedaan en zie uw reactie graag tegemoet.</p>';
    $out .= '<p>Met vriendelijke groet,<br>Sponsorcommissie Rohda Raalte<br>Tino Nijland ( sponsorkleding@rohdaraalte.nl ) 06-36305559</p>';
    return $out;
}

function quoteLetterPdfBytes(array $letter): string {
    $lines = quoteLetterPlainLines($letter);
    $pages = [[]];
    $yStart = 800;
    $y = $yStart;
    $lh = 14;
    $bottom = 52;
    foreach ($lines as $row) {
        $wrapped = quoteLetterWrap($row['text'], 92);
        foreach ($wrapped as $text) {
            if ($y < $bottom) {
                $pages[] = [];
                $y = $yStart;
            }
            $pages[count($pages) - 1][] = [
                'text' => $text,
                'diff' => !empty($row['diff']),
                'bold' => !empty($row['bold']),
                'y' => $y,
                'gap' => $row['gap'] ?? $lh,
            ];
            $y -= (float) ($row['gap'] ?? $lh);
        }
    }

    $objs = [];
    $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $kids = [];
    $contentIds = [];
    $pageCount = count($pages);
    $fontRegular = 3 + $pageCount * 2;
    $fontBold = $fontRegular + 1;
    $nextId = 3;
    foreach ($pages as $i => $items) {
        $contentId = $nextId + 1;
        $kids[] = $nextId . ' 0 R';
        $contentIds[$i] = $contentId;
        $stream = "BT\n";
        foreach ($items as $item) {
            $font = !empty($item['bold']) ? 'F2' : 'F1';
            $stream .= '0 g' . "\n";
            if (!empty($item['diff'])) {
                $stream .= "0.75 0.05 0.05 rg\n";
            } else {
                $stream .= "0 0 0 rg\n";
            }
            $stream .= '/' . $font . " 11 Tf\n";
            $stream .= '1 0 0 1 56 ' . sprintf('%.1f', $item['y']) . " Tm\n";
            $stream .= '(' . quoteLetterPdfEsc(quoteLetterToWin($item['text'])) . ") Tj\n";
        }
        $stream .= "ET\n";
        $objs[$nextId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $fontRegular . ' 0 R /F2 ' . $fontBold . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
        $objs[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';
        $nextId += 2;
    }
    $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
    $objs[$fontRegular] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>';
    $objs[$fontBold] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>';

    ksort($objs, SORT_NUMERIC);
    $pdf = "%PDF-1.4\n";
    $xref = [0];
    foreach ($objs as $id => $body) {
        $xref[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
    }
    $max = max(array_keys($objs));
    $xrefPos = strlen($pdf);
    $pdf .= 'xref' . "\n0 " . ($max + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) {
        $pdf .= sprintf('%010d 00000 n ', $xref[$i] ?? 0) . "\n";
    }
    $pdf .= 'trailer << /Size ' . ($max + 1) . ' /Root 1 0 R >>' . "\nstartxref\n" . $xrefPos . "\n%%EOF";
    return $pdf;
}

function quoteLetterPlainLines(array $letter): array {
    $rows = [];
    $add = static function (string $text, bool $diff = false, bool $bold = false, float $gap = 14) use (&$rows): void {
        $rows[] = ['text' => $text, 'diff' => $diff, 'bold' => $bold, 'gap' => $gap];
    };
    $add($letter['team'], false, true, 13);
    $add($letter['contact']);
    $add('Sponsor: ' . $letter['sponsor'], false, false, 18);
    $add($letter['place'] . ', ' . $letter['date'], false, false, 18);
    $add('Beste ' . $letter['first'] . ',', false, false, 16);
    $add('Hartelijk dank voor jullie offerteaanvraag. Hierbij bied ik geheel vrijblijvend onderstaande goederen aan.', false, false, 18);

    foreach ($letter['blocks'] as $block) {
        $title = (string) $block['qty'] . ' ' . (string) $block['name'];
        $art = trim((string) $block['article']);
        if ($art !== '') {
            $title .= ', ' . $art;
            if (!empty($block['quote_article'])) {
                $title .= ' (offerte ' . (string) $block['quote_article'] . ')';
            }
        }
        $color = trim((string) $block['color']);
        if ($color !== '') {
            $title .= ', kleur: ' . $color;
        }
        $add($title, !empty($block['qty_diff']) || !empty($block['sku_diff']), false, 13);
        foreach ($block['bands'] as $band) {
            $bits = [];
            $bandDiff = !empty($band['price_diff']);
            foreach ($band['parts'] as $part) {
                $showQty = !empty($part['only_quote']) ? (int) $part['quote_qty'] : (int) $part['qty'];
                $piece = quoteLetterSizePair($showQty, (string) $part['size']);
                if (!empty($part['only_quote'])) {
                    $piece .= ' (niet in Kitroom)';
                } elseif (!empty($part['diff']) && (int) $part['quote_qty'] !== (int) $part['qty']) {
                    $piece .= ' (offerte ' . (int) $part['quote_qty'] . ')';
                }
                $bits[] = $piece;
                if (!empty($part['diff'])) {
                    $bandDiff = true;
                }
            }
            $line = (string) $band['label'] . ': ' . implode(', ', $bits);
            if (($band['price'] ?? null) !== null) {
                $line .= ' ' . quoteLetterEuro((float) $band['price']);
            }
            $add($line, $bandDiff, false, 13);
        }
        $add('', false, false, 8);
    }

    foreach ($letter['prints'] as $p) {
        $appN = (int) $p['qty'];
        $got = (int) $p['quote_qty'];
        $showQty = !empty($p['only_quote']) ? $got : $appN;
        $line = (string) $showQty . ' ' . (string) $p['name'];
        if (($p['price'] ?? null) !== null) {
            $line .= ' ' . quoteLetterEuro((float) $p['price']);
        }
        if (!empty($p['only_quote'])) {
            $line .= ' (niet in Kitroom)';
        } elseif (!empty($p['diff']) && $appN !== $got) {
            $line .= ' (offerte ' . $got . ')';
        }
        $add($line, !empty($p['diff']), false, 14);
    }

    if (!empty($letter['extra'])) {
        $add('', false, false, 10);
        $add('Staat op de offerte, niet in Kitroom:', true, false, 14);
        foreach ($letter['extra'] as $ex) {
            $bits = [(string) (int) $ex['qty'], trim((string) $ex['name'])];
            if (trim((string) $ex['article']) !== '') {
                $bits[] = (string) $ex['article'];
            }
            if (trim((string) $ex['size']) !== '') {
                $bits[] = (string) $ex['size'];
            }
            $add(trim(implode(' ', array_filter($bits))), true, false, 13);
        }
    }

    $add('', false, false, 10);
    $add('Totaalprijs kleding en tassen ' . quoteLetterEuro((float) $letter['clothing_total']), !empty($letter['clothing_diff']), false, 14);
    $add('Drukwerk divers ' . quoteLetterEuro((float) $letter['print_total']), !empty($letter['print_diff']), false, 18);
    $add('Prijzen: per stuk, netto, exclusief btw.');
    $add('Betaling: binnen 14 dagen na levering.');
    $add('Levertijd: ca. 3 weken.', false, false, 16);
    $add('Ik vertrouw hiermee een interessante aanbieding te hebben gedaan en zie uw reactie graag tegemoet.', false, false, 16);
    $add('Met vriendelijke groet,');
    $add('Sponsorcommissie Rohda Raalte');
    $add('Tino Nijland ( sponsorkleding@rohdaraalte.nl ) 06-36305559');
    return $rows;
}

function quoteLetterWrap(string $text, int $width): array {
    $text = trim($text);
    if ($text === '') {
        return [''];
    }
    $words = preg_split('/\s+/u', $text) ?: [$text];
    $lines = [];
    $cur = '';
    foreach ($words as $w) {
        $try = $cur === '' ? $w : $cur . ' ' . $w;
        if (mb_strlen($try, 'UTF-8') > $width && $cur !== '') {
            $lines[] = $cur;
            $cur = $w;
        } else {
            $cur = $try;
        }
    }
    if ($cur !== '') {
        $lines[] = $cur;
    }
    return $lines !== [] ? $lines : [''];
}

function quoteLetterToWin(string $text): string {
    $text = str_replace(['€', "\u{20AC}"], "\x80", $text);
    $converted = function_exists('iconv')
        ? @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text)
        : @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    return is_string($converted) ? $converted : $text;
}

function quoteLetterPdfEsc(string $s): string {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}

function sendQuoteLetterPdf(array $letter): void {
    $bytes = quoteLetterPdfBytes($letter);
    $name = 'kitroom-14-2-offerte-' . (string) ($letter['when'] ?? date('d-m-Y')) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    header('Content-Length: ' . (string) strlen($bytes));
    echo $bytes;
    exit;
}

function renderQuoteLetterPage(array $letter): void {
    $title = 'Offerte 14-2';
    $note = !empty($letter['has_quote'])
        ? ((int) $letter['diff_count'] . ' afwijkingen t.o.v. ' . (string) $letter['quote_file'] . ' · rood = anders dan Prowork')
        : 'Geen offerte geladen: dit is de Kitroom-bestelling in offertevorm.';
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . '</title><style>
@page{size:A4;margin:18mm 18mm 20mm}
html,body{background:#e8e8e8;color:#111;margin:0;font:12pt/1.45 "Times New Roman",Times,serif}
.letter-bar{position:sticky;top:0;z-index:5;display:flex;flex-wrap:wrap;gap:8px;align-items:center;
  padding:10px 14px;background:#111;color:#fff;font:12.5px/1.3 system-ui,sans-serif}
.letter-bar a,.letter-bar button{border:1px solid #444;background:#fff;color:#111;border-radius:999px;
  padding:8px 14px;font:700 12.5px system-ui,sans-serif;text-decoration:none;cursor:pointer}
.letter-bar a.dark,.letter-bar button.dark{background:#c9a24a;border-color:#c9a24a}
.letter-bar span{flex:1 1 220px;font-size:12px;opacity:.92}
.sheet{max-width:210mm;margin:18px auto 40px;background:#fff;padding:18mm 18mm 16mm;min-height:277mm;
  box-shadow:0 8px 28px rgba(0,0,0,.12)}
.sheet p{margin:0 0 10px}
.sheet .head{margin-bottom:14px}
.sheet .item{margin:0 0 8px}
.diff{color:#c1121f;font-weight:700}
@media print{
  html,body{background:#fff}
  .letter-bar{display:none !important}
  .sheet{margin:0;padding:0;box-shadow:none;min-height:0;max-width:none}
  .diff{color:#c1121f;-webkit-print-color-adjust:exact;print-color-adjust:exact}
}
</style></head><body>';
    echo '<div class="letter-bar"><a href="./#bestel">Terug</a>';
    echo '<a class="dark" href="?pdf=offerte&amp;download=1">Download PDF</a>';
    echo '<button type="button" class="dark" onclick="window.print()">Opslaan als PDF</button>';
    echo '<span>' . h($note) . '</span></div>';
    echo '<div class="sheet">' . quoteLetterHtml($letter) . '</div>';
    echo '</body></html>';
    exit;
}
