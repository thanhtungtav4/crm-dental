<?php

declare(strict_types=1);

/**
 * Visual regression checker for PNG snapshots.
 *
 * Usage:
 * php scripts/check_visual_regression.php \
 *   --manifest=output/playwright/flow-check/visual-regression.manifest.json \
 *   --report=output/playwright/flow-check/visual-regression-check.txt \
 *   --diff-dir=output/playwright/diff/ci
 */

function parseArgs(array $argv): array
{
    $args = [
        'manifest' => null,
        'report' => 'output/playwright/flow-check/visual-regression-check.txt',
        'diff-dir' => null,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if (! str_starts_with($arg, '--')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, null);
        if ($value === null) {
            $value = '1';
        }

        if (array_key_exists($key, $args)) {
            $args[$key] = $value;
        }
    }

    return $args;
}

function loadPng(string $path)
{
    if (! file_exists($path)) {
        throw new RuntimeException("File not found: {$path}");
    }

    $img = @imagecreatefrompng($path);
    if ($img === false) {
        throw new RuntimeException("Failed to open PNG: {$path}");
    }

    return $img;
}

function writeOverlayDiff($candidateImg, $baselineImg, string $outputPath, int $threshold): void
{
    $width = imagesx($candidateImg);
    $height = imagesy($candidateImg);

    $overlay = imagecreatetruecolor($width, $height);

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $a = imagecolorat($baselineImg, $x, $y);
            $b = imagecolorat($candidateImg, $x, $y);

            $ar = ($a >> 16) & 0xff;
            $ag = ($a >> 8) & 0xff;
            $ab = $a & 0xff;

            $br = ($b >> 16) & 0xff;
            $bg = ($b >> 8) & 0xff;
            $bb = $b & 0xff;

            $maxDiff = max(abs($ar - $br), abs($ag - $bg), abs($ab - $bb));

            if ($maxDiff > $threshold) {
                // Blend candidate pixel with red marker for quick hotspot scan.
                $r = (int) round(($br * 0.65) + (255 * 0.35));
                $g = (int) round($bg * 0.65);
                $bl = (int) round($bb * 0.65);
            } else {
                $r = $br;
                $g = $bg;
                $bl = $bb;
            }

            imagesetpixel($overlay, $x, $y, ($r << 16) | ($g << 8) | $bl);
        }
    }

    $dir = dirname($outputPath);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    imagepng($overlay, $outputPath);
    imagedestroy($overlay);
}

function compareImages(string $baselinePath, string $candidatePath): array
{
    $baselineImg = loadPng($baselinePath);
    $candidateImg = loadPng($candidatePath);

    $width = imagesx($baselineImg);
    $height = imagesy($baselineImg);

    if ($width !== imagesx($candidateImg) || $height !== imagesy($candidateImg)) {
        throw new RuntimeException(
            "Dimension mismatch: baseline {$width}x{$height}, candidate "
            . imagesx($candidateImg) . 'x' . imagesy($candidateImg)
        );
    }

    $pixels = $width * $height;

    $changed10 = 0;
    $changed20 = 0;
    $changed40 = 0;

    $absSum = 0.0;
    $sqSum = 0.0;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $a = imagecolorat($baselineImg, $x, $y);
            $b = imagecolorat($candidateImg, $x, $y);

            $ar = ($a >> 16) & 0xff;
            $ag = ($a >> 8) & 0xff;
            $ab = $a & 0xff;

            $br = ($b >> 16) & 0xff;
            $bg = ($b >> 8) & 0xff;
            $bb = $b & 0xff;

            $dr = abs($ar - $br);
            $dg = abs($ag - $bg);
            $db = abs($ab - $bb);

            $maxDiff = max($dr, $dg, $db);

            if ($maxDiff > 10) {
                $changed10++;
            }

            if ($maxDiff > 20) {
                $changed20++;
            }

            if ($maxDiff > 40) {
                $changed40++;
            }

            $absSum += $dr + $dg + $db;
            $sqSum += ($dr * $dr) + ($dg * $dg) + ($db * $db);
        }
    }

    $channels = $pixels * 3;

    $result = [
        'width' => $width,
        'height' => $height,
        'changed10_pct' => ($changed10 / $pixels) * 100,
        'changed20_pct' => ($changed20 / $pixels) * 100,
        'changed40_pct' => ($changed40 / $pixels) * 100,
        'mae' => $absSum / $channels,
        'rmse' => sqrt($sqSum / $channels),
        'baseline_img' => $baselineImg,
        'candidate_img' => $candidateImg,
    ];

    return $result;
}

$args = parseArgs($argv);

if (empty($args['manifest'])) {
    fwrite(STDERR, "Missing --manifest option.\n");
    exit(2);
}

$manifestPath = (string) $args['manifest'];
$reportPath = (string) $args['report'];
$diffDir = $args['diff-dir'] !== null ? (string) $args['diff-dir'] : null;

if (! file_exists($manifestPath)) {
    fwrite(STDERR, "Manifest not found: {$manifestPath}\n");
    exit(2);
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);
if (! is_array($manifest) || ! isset($manifest['cases']) || ! is_array($manifest['cases'])) {
    fwrite(STDERR, "Invalid manifest format: {$manifestPath}\n");
    exit(2);
}

$defaults = $manifest['defaults'] ?? [];
$thresholdChanged20 = (float) ($defaults['max_changed20_pct'] ?? 3.0);
$thresholdMae = (float) ($defaults['max_mae'] ?? 3.0);

$lines = [];
$lines[] = 'Visual regression check';
$lines[] = 'Date: ' . date('Y-m-d H:i:s');
$lines[] = 'Manifest: ' . $manifestPath;
$lines[] = 'Thresholds (default): changed20 <= ' . $thresholdChanged20 . '%, mae <= ' . $thresholdMae;
$lines[] = '';

$failed = false;

foreach ($manifest['cases'] as $case) {
    $id = (string) ($case['id'] ?? 'unknown');
    $baseline = (string) ($case['baseline'] ?? '');
    $candidate = (string) ($case['candidate'] ?? '');

    $caseChanged20 = (float) ($case['max_changed20_pct'] ?? $thresholdChanged20);
    $caseMae = (float) ($case['max_mae'] ?? $thresholdMae);

    if ($baseline === '' || $candidate === '') {
        $failed = true;
        $lines[] = "[{$id}] FAIL - missing baseline/candidate path in manifest";
        $lines[] = '';
        continue;
    }

    try {
        $result = compareImages($baseline, $candidate);

        $status = ($result['changed20_pct'] <= $caseChanged20 && $result['mae'] <= $caseMae)
            ? 'PASS'
            : 'FAIL';

        if ($status === 'FAIL') {
            $failed = true;
        }

        $lines[] = "[{$id}] {$status}";
        $lines[] = "- baseline: {$baseline}";
        $lines[] = "- candidate: {$candidate}";
        $lines[] = sprintf('- size: %dx%d', $result['width'], $result['height']);
        $lines[] = sprintf('- changed@10: %.3f%%', $result['changed10_pct']);
        $lines[] = sprintf('- changed@20: %.3f%% (max %.3f%%)', $result['changed20_pct'], $caseChanged20);
        $lines[] = sprintf('- changed@40: %.3f%%', $result['changed40_pct']);
        $lines[] = sprintf('- mae: %.3f (max %.3f)', $result['mae'], $caseMae);
        $lines[] = sprintf('- rmse: %.3f', $result['rmse']);

        if ($diffDir !== null) {
            $overlayPath = rtrim($diffDir, '/\\') . '/overlay-' . $id . '.png';
            writeOverlayDiff($result['candidate_img'], $result['baseline_img'], $overlayPath, 20);
            $lines[] = '- overlay: ' . $overlayPath;
        }

        $lines[] = '';

        imagedestroy($result['baseline_img']);
        imagedestroy($result['candidate_img']);
    } catch (Throwable $e) {
        $failed = true;
        $lines[] = "[{$id}] FAIL - " . $e->getMessage();
        $lines[] = '';
    }
}

$lines[] = 'Overall: ' . ($failed ? 'FAIL' : 'PASS');

$reportDir = dirname($reportPath);
if (! is_dir($reportDir)) {
    mkdir($reportDir, 0777, true);
}

file_put_contents($reportPath, implode(PHP_EOL, $lines) . PHP_EOL);

echo "Report written: {$reportPath}" . PHP_EOL;
exit($failed ? 1 : 0);
