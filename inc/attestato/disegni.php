<?php
/**
 * Elementi grafici condivisi da anteprima HTML (SVG) e PDF: paperelle, podio, medaglie.
 */
declare(strict_types=1);

/* --------------------------------------------------------------- paperelle */

/**
 * Posizioni (mm, A4 orizzontale) delle paperelle in basso a sinistra: le usano
 * sia il PDF sia l'anteprima HTML (paperelle_svg), cosi' restano allineati.
 */
const PAPERELLE_DIPLOMA = [
    'impronte' => [[64, 198.1], [67.2, 198.1], [70.4, 198.1], [73.6, 198.1], [76.8, 198.1], [80, 198.1]],
    'papere'   => [[88, 198.7, 5.2], [96, 198.7, 5.2], [104, 198.7, 5.2], [116, 198.7, 9]], // piedi x, y, altezza
    'fumetto'  => [97, 178.5, 27, 6.2],                                                       // x, y, larghezza, altezza
    'punta'    => [[114, 184.7], [117.5, 184.7], [118.5, 188.2]],
    'testo'    => 'Quack, World!',
];

/** Numero per gli attributi SVG: 2 decimali al massimo, niente zeri inutili. */
function svg_num(float $n): string
{
    return rtrim(rtrim(sprintf('%.2F', $n), '0'), '.');
}

function svg_punti(array $punti): string
{
    return implode(' ', array_map(static fn(array $p): string => svg_num($p[0]) . ',' . svg_num($p[1]), $punti));
}

/** Paperella in SVG: stessa forma di DiplomaPdf::Paperella (piedi in x, y; alta h mm). */
function papera_svg(float $x, float $y, float $h, bool $corona = false): string
{
    $f = 'svg_num';
    $out = '<g stroke-width="' . $f(max(0.15, $h * 0.025)) . '">'
        . '<path d="M' . $f($x - .1 * $h) . ' ' . $f($y - .12 * $h) . 'V' . $f($y) . 'h' . $f(.12 * $h)
        . 'M' . $f($x + .1 * $h) . ' ' . $f($y - .12 * $h) . 'V' . $f($y) . 'h' . $f(.12 * $h) . '" stroke="#ff8c1a" fill="none"/>'
        . '<g fill="#ffd84d" stroke="#c89600">'
        . '<polygon points="' . svg_punti([[$x - .38 * $h, $y - .42 * $h], [$x - .62 * $h, $y - .62 * $h], [$x - .5 * $h, $y - .3 * $h]]) . '"/>'
        . '<ellipse cx="' . $f($x) . '" cy="' . $f($y - .36 * $h) . '" rx="' . $f(.48 * $h) . '" ry="' . $f(.25 * $h) . '"/>'
        . '<circle cx="' . $f($x + .33 * $h) . '" cy="' . $f($y - .73 * $h) . '" r="' . $f(.22 * $h) . '"/></g>'
        . '<ellipse cx="' . $f($x - .06 * $h) . '" cy="' . $f($y - .38 * $h) . '" rx="' . $f(.24 * $h) . '" ry="' . $f(.12 * $h) . '" fill="#f2c200"/>'
        . '<polygon points="' . svg_punti([[$x + .5 * $h, $y - .78 * $h], [$x + .75 * $h, $y - .71 * $h], [$x + .5 * $h, $y - .64 * $h]]) . '" fill="#ff8c1a" stroke="#c95f00"/>'
        . '<circle cx="' . $f($x + .4 * $h) . '" cy="' . $f($y - .78 * $h) . '" r="' . $f(.035 * $h) . '" fill="#1a1a1a"/>';
    if ($corona) {
        $out .= '<polygon points="' . svg_punti(punti_corona($x, $y, $h)) . '" fill="#ffd84d" stroke="#c89600"/>';
    }
    return $out . '</g>';
}

/** Coroncina sulla testa della paperella prima classificata. */
function punti_corona(float $x, float $y, float $h): array
{
    $cx = $x + 0.33 * $h;
    $b = $y - 0.9 * $h;   // base, appoggiata sulla testa
    $t = $y - 1.12 * $h;  // punte
    $w = 0.17 * $h;
    return [[$cx - $w, $b], [$cx - $w, $t], [$cx - $w / 2, $b - 0.1 * $h], [$cx, $t - 0.02 * $h],
            [$cx + $w / 2, $b - 0.1 * $h], [$cx + $w, $t], [$cx + $w, $b]];
}

/** Le paperelle del diploma in SVG (viewBox in mm del foglio) per l'anteprima in diploma.php. */
function paperelle_svg(): string
{
    $f = 'svg_num';
    $out = '';
    foreach (PAPERELLE_DIPLOMA['impronte'] as [$x, $y]) {
        $out .= '<circle cx="' . $f($x) . '" cy="' . $f($y) . '" r=".45" fill="#cde1f0"/>';
    }
    foreach (PAPERELLE_DIPLOMA['papere'] as [$x, $y, $h]) {
        $out .= papera_svg($x, $y, $h);
    }
    [$fx, $fy, $fw, $fh] = PAPERELLE_DIPLOMA['fumetto'];
    $out .= '<g fill="#fff" stroke="#5cc8ff" stroke-width=".3">'
        . '<polygon points="' . svg_punti(PAPERELLE_DIPLOMA['punta']) . '"/>'
        . '<rect x="' . $f($fx) . '" y="' . $f($fy) . '" width="' . $f($fw) . '" height="' . $f($fh) . '"/>'
        . '<rect x="' . $f($fx + 17.2) . '" y="' . $f($fy + $fh - .5) . '" width="3.1" height=".7" stroke="none"/></g>'
        . '<text x="' . $f($fx + $fw / 2) . '" y="' . $f($fy + 4.2) . '" text-anchor="middle" font-family="Courier New, Courier, monospace" font-size="2.65" fill="#0b1020">'
        . htmlspecialchars(PAPERELLE_DIPLOMA['testo'], ENT_QUOTES) . '</text>';
    return '<svg class="paperelle" viewBox="0 0 297 210" aria-hidden="true">' . $out . '</svg>';
}

/* --------------------------------------------------------------- podio */

/** Colori della medaglia: [nome, riempimento, bordo] */
const MEDAGLIE = [
    1 => ['ORO',     [255, 216, 77],  [200, 150, 0]],
    2 => ['ARGENTO', [214, 219, 228], [128, 136, 152]],
    3 => ['BRONZO',  [233, 170, 115], [160, 95, 45]],
];

/** Gradini del podio (mm): posizione => [x, y del piano, larghezza]; la base e' a PODIO_BASE. */
const PODIO = [2 => [112.5, 149, 24], 1 => [136.5, 144, 24], 3 => [160.5, 152, 24]];
const PODIO_BASE = 160.0;

function rgb_hex(array $c): string
{
    return sprintf('#%02x%02x%02x', ...$c);
}

/** Il podio con le paperelle in SVG, per l'anteprima del certificato di merito. */
function podio_svg(int $posto): string
{
    $f = 'svg_num';
    $out = '';
    foreach (PODIO as $n => [$x, $y, $w]) {
        $mio = $n === $posto;
        $out .= '<rect x="' . $f($x) . '" y="' . $f($y) . '" width="' . $f($w) . '" height="' . $f(PODIO_BASE - $y) . '" fill="'
            . ($mio ? rgb_hex(MEDAGLIE[$n][1]) : '#eef7fd') . '" stroke="' . ($mio ? rgb_hex(MEDAGLIE[$n][2]) : '#5cc8ff') . '" stroke-width=".35"/>'
            . '<text x="' . $f($x + $w / 2) . '" y="' . $f($y + 6.2) . '" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-weight="700" font-size="5" fill="#0b1020">' . $n . '</text>'
            . papera_svg($x + $w / 2 - 0.5, $y - 0.2, $n === 1 ? 10 : 8.5, $n === 1);
    }
    return '<svg class="paperelle" viewBox="0 0 297 210" aria-hidden="true">' . $out . '</svg>';
}
