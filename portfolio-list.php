<?php
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

function normalize_utf8(string $s): string {
  // Si ya es UTF-8 válido, no tocamos
  if (mb_check_encoding($s, 'UTF-8')) return $s;
  // Intento 1: lo tratamos como ISO-8859-1
  $t = @mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
  if ($t !== false && mb_check_encoding($t, 'UTF-8')) return $t;
  // Intento 2: eliminar bytes inválidos
  $t = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
  return $t !== false ? $t : '';
}

function zlib_decompress(string $data): ?string {
  // PNG zTXt/iTXt usan ZLIB stream (no raw deflate)
  $out = @zlib_decode($data);
  if ($out !== false) return $out;
  $out = @gzuncompress($data);
  if ($out !== false) return $out;
  // Último recurso: a veces viene como raw deflate
  $out = @gzinflate($data);
  if ($out !== false) return $out;
  return null;
}

function read_png_text_chunks(string $path): array {
  $out = [];
  $f = @fopen($path, 'rb');
  if (!$f) return $out;

  // Firma PNG
  $sig = fread($f, 8);
  if ($sig !== "\x89PNG\r\n\x1a\n") { fclose($f); return $out; }

  while (!feof($f)) {
    $lenData = fread($f, 4);
    $type    = fread($f, 4);
    if (strlen($lenData) < 4 || strlen($type) < 4) break;

    $len = unpack('N', $lenData)[1];
    $data = ($len > 0) ? fread($f, $len) : '';
    fread($f, 4); // CRC (ignoramos)

    if ($type === 'tEXt') {
      $pos = strpos($data, "\0");
      if ($pos !== false) {
        $key = substr($data, 0, $pos);
        $txt = substr($data, $pos + 1);
        // tEXt → ISO-8859-1 por especificación
        $txt = normalize_utf8($txt);
        $out[$key] = $txt;
      }

    } elseif ($type === 'zTXt') {
      // keyword\0 compressionMethod(1) compressedText(zlib)
      $pos = strpos($data, "\0");
      if ($pos !== false && strlen($data) > $pos + 2) {
        $key = substr($data, 0, $pos);
        $method = ord($data[$pos + 1]); // 0 = deflate (zlib)
        $comp = substr($data, $pos + 2);
        if ($method === 0) {
          $txt = zlib_decompress($comp);
          if ($txt !== null) {
            $txt = normalize_utf8($txt);
            $out[$key] = $txt;
          }
        }
      }

    } elseif ($type === 'iTXt') {
      // keyword\0 compFlag(1) compMethod(1) language\0 translated\0 text(utf8|zlib)
      $p0 = strpos($data, "\0");
      if ($p0 !== false && strlen($data) > $p0 + 2) {
        $key = substr($data, 0, $p0);
        $compressionFlag = ord($data[$p0 + 1]);
        $compressionMethod = ord($data[$p0 + 2]);
        $rest = substr($data, $p0 + 3);

        $p1 = strpos($rest, "\0"); if ($p1 === false) goto nextchunk;
        $rest2 = substr($rest, $p1 + 1);

        $p2 = strpos($rest2, "\0"); if ($p2 === false) goto nextchunk;
        $text = substr($rest2, $p2 + 1);

        if ($compressionFlag === 1 && $compressionMethod === 0) {
          $decomp = zlib_decompress($text);
          if ($decomp !== null) $text = $decomp;
        }
        // iTXt es UTF-8 por especificación
        $out[$key] = normalize_utf8($text);
      }

    } elseif ($type === 'IEND') {
      break;
    }
    nextchunk:
    // continuar
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

  $txt = read_png_text_chunks($path);

  $desc = null;
  $candidates = ['Description','description','ImageDescription','Comment','comment','Title','title','Caption','caption','Descripcion','descripción','Descripción'];
  foreach ($candidates as $k) {
    if (isset($txt[$k])) {
      $val = trim((string)$txt[$k]);
      if ($val !== '') { $desc = $val; break; }
    }
  }

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

// AÑADE este flag para evitar fallos por UTF-8 inválido
$json = json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if ($json === false) {
  http_response_code(500);
  echo json_encode([
    'error' => 'JSON encoding failed',
    'json_last_error' => json_last_error_msg(),
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}
echo $json;

