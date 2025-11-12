<?php
/**
 * Returns a JSON array of PNG files found in ./portfolio directory.
 * Each item: { src, title, mtime, size, dim, desc }
 * - Reads PNG text chunks (tEXt, zTXt, iTXt). Prefer keys like Description/Comment/Title.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$dir = __DIR__ . DIRECTORY_SEPARATOR . 'portfolio';
$baseUrl = dirname($_SERVER['SCRIPT_NAME'] ?? '') . '/portfolio';
$baseUrl = rtrim($baseUrl, '/');

if (!is_dir($dir)) {
  http_response_code(500);
  echo json_encode(['error' => 'Portfolio directory not found: '.$dir], JSON_UNESCAPED_SLASHES);
  exit;
}

function read_png_text_chunks(string $path): array {
  $out = [];
  $f = @fopen($path, 'rb');
  if (!$f) return $out;

  // Validate PNG signature
  $sig = fread($f, 8);
  if ($sig !== "\x89PNG\r\n\x1a\n") { fclose($f); return $out; }

  while (!feof($f)) {
    $lenData = fread($f, 4);
    $type    = fread($f, 4);
    if (strlen($lenData) < 4 || strlen($type) < 4) break;

    $len = unpack('N', $lenData)[1];
    $data = ($len > 0) ? fread($f, $len) : '';
    $crc  = fread($f, 4); // skip CRC

    if ($type === 'tEXt') {
      $pos = strpos($data, "\0");
      if ($pos !== false) {
        $key = substr($data, 0, $pos);
        $txt = substr($data, $pos + 1);
        // PNG tEXt is ISO-8859-1; treat as UTF-8 where possible
        if (!mb_check_encoding($txt, 'UTF-8')) {
          $txt = mb_convert_encoding($txt, 'UTF-8', 'ISO-8859-1');
        }
        $out[$key] = $txt;
      }
    } elseif ($type === 'zTXt') {
      // keyword\0compressionMethod(1)\compressedText
      $pos = strpos($data, "\0");
      if ($pos !== false && strlen($data) > $pos + 2) {
        $key = substr($data, 0, $pos);
        $method = ord($data[$pos + 1]); // 0=deflate
        $comp = substr($data, $pos + 2);
        if ($method === 0) {
          $txt = @gzinflate($comp);
          if ($txt !== false) {
            if (!mb_check_encoding($txt, 'UTF-8')) {
              $txt = mb_convert_encoding($txt, 'UTF-8', 'ISO-8859-1');
            }
            $out[$key] = $txt;
          }
        }
      }
    } elseif ($type === 'iTXt') {
      // keyword\0 compressionFlag(1) compressionMethod(1) languageTag\0 translatedKeyword\0 text
      $p0 = strpos($data, "\0");
      if ($p0 !== false && strlen($data) > $p0 + 2) {
        $key = substr($data, 0, $p0);
        $compressionFlag = ord($data[$p0 + 1]);
        $compressionMethod = ord($data[$p0 + 2]);
        $rest = substr($data, $p0 + 3);

        // languageTag\0
        $p1 = strpos($rest, "\0");
        if ($p1 === false) { /* malformed */ goto nextchunk; }
        $rest2 = substr($rest, $p1 + 1);

        // translatedKeyword\0
        $p2 = strpos($rest2, "\0");
        if ($p2 === false) { /* malformed */ goto nextchunk; }
        $text = substr($rest2, $p2 + 1);

        if ($compressionFlag === 1 && $compressionMethod === 0) {
          $decomp = @gzinflate($text);
          if ($decomp !== false) $text = $decomp;
        }
        // iTXt is UTF-8 by spec
        $out[$key] = $text;
      }
    } elseif ($type === 'IEND') {
      break;
    }
    nextchunk:
    // continue
  }
  fclose($f);
  return $out;
}

$files = glob($dir . DIRECTORY_SEPARATOR . '*.png', GLOB_NOSORT);
if ($files === false) $files = [];
natcasesort($files);

$out = [];
foreach ($files as $path) {
  if (!is_file($path)) continue;

  $rel = $baseUrl . '/' . rawurlencode(basename($path));
  $stat = @stat($path);
  $mtime = $stat ? ($stat['mtime'] ?? null) : null;
  $size = @filesize($path);

  $dim = null;
  $info = @getimagesize($path);
  if ($info && isset($info[0], $info[1])) $dim = $info[0] . '×' . $info[1];

  // Parse embedded PNG text tags
  $txt = read_png_text_chunks($path);
  // Choose a description from common keys (case-insensitive)
  $desc = null;
  $candidates = ['Description','description','ImageDescription','Comment','comment','Title','title','Caption','caption','Descripcion','descripción','Descripción'];
  foreach ($candidates as $k) {
    if (isset($txt[$k]) && trim((string)$txt[$k]) !== '') { $desc = trim((string)$txt[$k]); break; }
  }

  // Title from filename if not provided by metadata
  $title = $desc ? null : preg_replace('/\.[^.]+$/', '', basename($path));
  if ($title !== null) {
    $title = preg_replace('/[_-]+/', ' ', (string)$title);
    $title = ucwords(trim($title));
  }

  $out[] = [
    'src'   => $rel,
    'title' => $title,
    'mtime' => $mtime,
    'size'  => $size ? (string)$size : null,
    'dim'   => $dim,
    'desc'  => $desc,
  ];
}

echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

