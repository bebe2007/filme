<?php
require __DIR__ . '/includes/functions.php';

$id = strtolower(trim((string)($_GET['id'] ?? '')));
$placeholder = __DIR__ . '/assets/img/person-placeholder.svg';
if (!preg_match('/^nm\d{5,12}$/', $id)) {
    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Cache-Control: public, max-age=86400');
    readfile($placeholder); exit;
}

$metaFile = ''; // Diskless image resolution
$positiveTtl = max(86400, (int)cfg('person_image_ttl'));
$negativeTtl = 3600;
$resolverVersion = 4;

function serve_person_image_url($url) {
    header('Cache-Control: public, max-age=604800, stale-while-revalidate=2592000');
    header('Referrer-Policy: no-referrer');
    header('Location: ' . $url, true, 302);
    exit;
}
function serve_person_placeholder($placeholder) {
    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Cache-Control: public, max-age=3600');
    readfile($placeholder); exit;
}
function valid_person_image_url($url) {
    if (!valid_image_url($url)) return false;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    return $host === 'image.tmdb.org' || $host === 'm.media-amazon.com' || str_ends_with($host, '.media-amazon.com');
}
$cacheKey='filme_photo_'.$id; $hit=false; $oldMeta=function_exists('apcu_fetch')?apcu_fetch($cacheKey,$hit):null; if(!$hit)$oldMeta=null;
if (is_array($oldMeta) && valid_person_image_url($oldMeta['url'] ?? '') && time() - (int)($oldMeta['checked'] ?? 0) < $positiveTtl) {
    serve_person_image_url($oldMeta['url']);
}
if (is_array($oldMeta) && (int)($oldMeta['resolver_version'] ?? 0) === $resolverVersion && empty($oldMeta['url']) && time() - (int)($oldMeta['checked'] ?? 0) < $negativeTtl) {
    serve_person_placeholder($placeholder);
}

$url = '';
if (tmdb_enabled()) {
    $doc = tmdb_find_document($id, 'ro-RO');
    $profile = $doc['person_results'][0]['profile_path'] ?? '';
    if (is_string($profile) && preg_match('~^/[A-Za-z0-9._/-]+$~', $profile)) $url = 'https://image.tmdb.org/t/p/w185'.$profile;
}
// Primary resolver: IMDb's web GraphQL endpoint. If its schema/availability changes, the HTML resolver below remains available.
if ($url === '' && function_exists('curl_init')) {
    $query = 'query NameImage($id: ID!) { name(id: $id) { primaryImage { url } } }';
    $payload = json_encode(['query'=>$query,'variables'=>['id'=>$id]], JSON_UNESCAPED_SLASHES);
    $ch = curl_init('https://api.graphql.imdb.com/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>6, CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140 Safari/537.36',
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Origin: https://www.imdb.com','Referer: https://www.imdb.com/'],
        CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
    ]);
    $raw = curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($raw !== false && $code >= 200 && $code < 300) {
        $doc = json_decode($raw, true);
        $candidate = $doc['data']['name']['primaryImage']['url'] ?? '';
        if (valid_person_image_url($candidate)) $url = $candidate;
    }
}

// Fallback resolver: parse the public IMDb name page robustly, independent of meta-tag attribute order.
if ($url === '') {
    $html = fetch_remote_now('https://www.imdb.com/name/'.$id.'/', 6, [
        'Accept-Language: en-US,en;q=0.8',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    ]);
    if ($html) {
        if (class_exists('DOMDocument')) {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            if (@$dom->loadHTML($html)) {
                foreach ($dom->getElementsByTagName('meta') as $meta) {
                    if (strtolower((string)$meta->getAttribute('property')) === 'og:image') {
                        $candidate = html_entity_decode((string)$meta->getAttribute('content'), ENT_QUOTES|ENT_HTML5, 'UTF-8');
                        if (valid_person_image_url($candidate)) { $url = $candidate; break; }
                    }
                }
            }
            libxml_clear_errors();
        }
        if ($url === '' && preg_match('/<meta[^>]+(?:property=["\']og:image["\'][^>]+content=["\']([^"\']+)|content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\'])/i', $html, $m)) {
            $candidate = html_entity_decode((string)($m[1] ?: $m[2]), ENT_QUOTES|ENT_HTML5, 'UTF-8');
            if (valid_person_image_url($candidate)) $url = $candidate;
        }
        if ($url === '' && preg_match('/"primaryImage"\s*:\s*\{[^}]*"url"\s*:\s*"([^"\r\n]+)"/i', $html, $m)) {
            $candidate = json_decode('"'.$m[1].'"');
            if (valid_person_image_url($candidate)) $url = $candidate;
        }
    }
}

if (valid_person_image_url($url)) {
    if(function_exists('apcu_store'))@apcu_store($cacheKey,['checked'=>time(),'url'=>$url,'resolver_version'=>$resolverVersion],$positiveTtl);
    serve_person_image_url($url);
}
if(function_exists('apcu_store'))@apcu_store($cacheKey,['checked'=>time(),'url'=>'','resolver_version'=>$resolverVersion],$negativeTtl);
serve_person_placeholder($placeholder);
