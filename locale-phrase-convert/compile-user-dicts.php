<?php
/**
 * Compile 國教院 NDJSON dictionaries into a browser phrase table.
 *
 * @package LocalePhraseConvert
 */

/**
 * Rebuild user-phrases.js when source JSON files are newer.
 *
 * @param string $dictsDir Directory of *.json dictionaries.
 * @param string $outPath  Output JS path.
 * @return bool True if the output file exists and is readable.
 */
function localePhraseConvertCompileUserPhrases( $dictsDir, $outPath ) {
	if ( ! is_dir( $dictsDir ) ) {
		return is_readable( $outPath );
	}

	$files = glob( $dictsDir . '/*.json' );
	if ( ! is_array( $files ) || $files === array() ) {
		return is_readable( $outPath );
	}

	$latestSource = 0;
	foreach ( $files as $filePath ) {
		$mtime = filemtime( $filePath );
		if ( $mtime !== false && $mtime > $latestSource ) {
			$latestSource = $mtime;
		}
	}

	$outMtime = is_readable( $outPath ) ? filemtime( $outPath ) : false;
	if ( is_int( $outMtime ) && $outMtime >= $latestSource ) {
		return true;
	}

	$toTaiwan   = array();
	$toMainland = array();
	foreach ( $files as $filePath ) {
		$basename     = basename( $filePath );
		$isLiterary   = stripos( $basename, 'literary' ) !== false;
		$minLength    = ( $isLiterary || stripos( $basename, 'synthesis' ) !== false ) ? 3 : 2;
		localePhraseConvertIngestDictFile( $filePath, $isLiterary, $minLength, $toTaiwan, $toMainland );
	}

	$payload = array(
		'toTaiwan'   => localePhraseConvertPairList( $toTaiwan ),
		'toMainland' => localePhraseConvertPairList( $toMainland ),
	);
	$json = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
	if ( ! is_string( $json ) || $json === '' ) {
		$error = function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : 'unknown JSON error';
		error_log( 'Locale phrase convert: failed to encode compiled user dictionaries: ' . $error );
		return is_readable( $outPath );
	}

	$written = file_put_contents( $outPath, 'window.LOCALE_PHRASE_USER_DICTS = ' . $json . ";\n", LOCK_EX );
	if ( $written === false ) {
		error_log( 'Locale phrase convert: failed to write ' . $outPath );
		return is_readable( $outPath );
	}

	return true;
}

/**
 * @param string $filePath Source JSON/NDJSON path.
 * @param bool   $requireSharedHan Literary tables need at least one shared character.
 * @param array<string, string> $toTaiwan Taiwan map.
 * @param array<string, string> $toMainland Mainland map.
 */
function localePhraseConvertIngestDictFile( $filePath, $requireSharedHan, $minLength, &$toTaiwan, &$toMainland ) {
	$handle = fopen( $filePath, 'rb' );
	if ( $handle === false ) {
		error_log( 'Locale phrase convert: cannot open dictionary ' . $filePath );
		return;
	}

	$firstChunk = '';
	$isNdjson   = true;
	while ( ( $line = fgets( $handle ) ) !== false ) {
		$trim = trim( $line );
		if ( $trim === '' ) {
			continue;
		}
		$firstChunk = $trim;
		$decoded    = json_decode( $trim, true );
		if ( is_array( $decoded ) && localePhraseConvertHasMoeFields( $decoded ) ) {
			localePhraseConvertAddMoeEntry( $decoded, $requireSharedHan, $minLength, $toTaiwan, $toMainland );
		} else {
			$isNdjson = false;
		}
		break;
	}

	if ( $isNdjson ) {
		while ( ( $line = fgets( $handle ) ) !== false ) {
			$trim = trim( $line );
			if ( $trim === '' ) {
				continue;
			}
			$decoded = json_decode( $trim, true );
			if ( is_array( $decoded ) ) {
				localePhraseConvertAddMoeEntry( $decoded, $requireSharedHan, $minLength, $toTaiwan, $toMainland );
			}
		}
		fclose( $handle );
		return;
	}

	fclose( $handle );
	$raw = file_get_contents( $filePath );
	if ( $raw === false ) {
		error_log( 'Locale phrase convert: failed to read dictionary ' . $filePath );
		return;
	}
	$parsed = json_decode( $raw, true );
	if ( ! is_array( $parsed ) ) {
		error_log( 'Locale phrase convert: invalid JSON dictionary ' . $filePath );
		return;
	}
	if ( isset( $parsed['toTaiwan'] ) || isset( $parsed['toMainland'] ) ) {
		localePhraseConvertMergePairMap( isset( $parsed['toTaiwan'] ) ? $parsed['toTaiwan'] : array(), $toTaiwan );
		localePhraseConvertMergePairMap( isset( $parsed['toMainland'] ) ? $parsed['toMainland'] : array(), $toMainland );
		return;
	}
	if ( isset( $parsed[0] ) && is_array( $parsed[0] ) ) {
		foreach ( $parsed as $entry ) {
			if ( is_array( $entry ) ) {
				localePhraseConvertAddMoeEntry( $entry, $requireSharedHan, $minLength, $toTaiwan, $toMainland );
			}
		}
	}
}

/**
 * @param array<mixed> $entry Dictionary row.
 * @return bool
 */
function localePhraseConvertHasMoeFields( $entry ) {
	return isset( $entry['台灣用語'] ) || isset( $entry['大陸用語'] );
}

/**
 * @param array<mixed>          $entry Dictionary row.
 * @param bool                  $requireSharedHan Require a shared Han character.
 * @param array<string, string> $toTaiwan Taiwan map.
 * @param array<string, string> $toMainland Mainland map.
 */
function localePhraseConvertAddMoeEntry( $entry, $requireSharedHan, $minLength, &$toTaiwan, &$toMainland ) {
	$mainland = localePhraseConvertCleanTerm( isset( $entry['大陸用語'] ) ? $entry['大陸用語'] : '' );
	$variants = localePhraseConvertTaiwanVariants( isset( $entry['台灣用語'] ) ? $entry['台灣用語'] : '' );
	$taiwan   = localePhraseConvertPickTaiwan( $variants, $mainland );
	if ( $taiwan === '' || $mainland === '' || $taiwan === $mainland ) {
		return;
	}
	if ( ! localePhraseConvertShouldKeepPair( $taiwan, $mainland, $requireSharedHan, $minLength ) ) {
		return;
	}
	if ( ! isset( $toTaiwan[ $mainland ] ) ) {
		$toTaiwan[ $mainland ] = $taiwan;
	}
	if ( ! isset( $toTaiwan[ $taiwan ] ) ) {
		$toTaiwan[ $taiwan ] = $taiwan;
	}
	if ( ! isset( $toMainland[ $taiwan ] ) ) {
		$toMainland[ $taiwan ] = $mainland;
	}
	if ( ! isset( $toMainland[ $mainland ] ) ) {
		$toMainland[ $mainland ] = $mainland;
	}
}

/**
 * @param mixed $raw Raw term.
 * @return string
 */
function localePhraseConvertCleanTerm( $raw ) {
	if ( ! is_string( $raw ) ) {
		return '';
	}
	$hadPosTag = strpos( $raw, '【' ) !== false;
	$text      = trim( $raw );
	if ( $text === '' || strcasecmp( $text, 'null' ) === 0 ) {
		return '';
	}
	$utf8 = iconv( 'UTF-8', 'UTF-8//IGNORE', $text );
	if ( is_string( $utf8 ) ) {
		$text = $utf8;
	}
	$text = preg_replace( '/【[^】]*】/u', '', $text );
	$text = preg_replace( '/\[[^\]]*\]/u', '', $text );
	$text = preg_replace( '/（[^）]*）/u', '', $text );
	$text = preg_replace( '/\([^)]*\)/u', '', $text );
	$text = preg_replace( '/｛[^｝]*｝/u', '', $text );
	$text = preg_replace( '/\{[^}]*\}/u', '', $text );
	if ( ! is_string( $text ) ) {
		return '';
	}
	$text = str_replace( array( "\u{0084}", "\xC2\xA0", '' ), '', $text );
	$text = preg_replace( '/\s+/u', '', $text );
	if ( ! is_string( $text ) ) {
		return '';
	}
	$text = trim( $text, " \t；;、,，/\\" );
	if ( $hadPosTag && preg_match( '/的$/u', $text ) ) {
		$text = preg_replace( '/的$/u', '', $text );
		if ( ! is_string( $text ) ) {
			return '';
		}
	}
	if ( $text === '' || mb_strlen( $text, 'UTF-8' ) > 24 ) {
		return '';
	}
	if ( preg_match( '/[A-Za-z]{3,}/', $text ) ) {
		return '';
	}
	return $text;
}

/**
 * @param mixed $raw Taiwan field.
 * @return array<int, string>
 */
function localePhraseConvertTaiwanVariants( $raw ) {
	$out = array();
	if ( ! is_string( $raw ) || $raw === '' ) {
		return $out;
	}
	$parts = preg_split( '/[；;]/u', $raw );
	if ( ! is_array( $parts ) ) {
		return $out;
	}
	foreach ( $parts as $part ) {
		$term = localePhraseConvertCleanTerm( $part );
		if ( $term !== '' ) {
			$out[] = $term;
		}
	}
	return $out;
}

/**
 * @param array<int, string> $variants Taiwan variants.
 * @param string             $mainland Mainland term.
 * @return string
 */
function localePhraseConvertPickTaiwan( $variants, $mainland ) {
	if ( $variants === array() || $mainland === '' ) {
		return '';
	}
	$mainlandLen = mb_strlen( $mainland, 'UTF-8' );
	$best        = '';
	$bestLen     = -1;
	foreach ( $variants as $term ) {
		$termLen = mb_strlen( $term, 'UTF-8' );
		if ( abs( $termLen - $mainlandLen ) > 2 ) {
			continue;
		}
		if ( $termLen > $bestLen ) {
			$best    = $term;
			$bestLen = $termLen;
		}
	}
	if ( $best !== '' ) {
		return $best;
	}
	return $variants[0];
}

/**
 * @param string $left First string.
 * @param string $right Second string.
 * @return int
 */
function localePhraseConvertSharedHanCount( $left, $right ) {
	$rightChars = preg_split( '//u', $right, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $rightChars ) ) {
		return 0;
	}
	$set   = array_flip( $rightChars );
	$leftChars = preg_split( '//u', $left, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $leftChars ) ) {
		return 0;
	}
	$count = 0;
	foreach ( $leftChars as $char ) {
		if ( isset( $set[ $char ] ) ) {
			$count++;
		}
	}
	return $count;
}

/**
 * @param string $taiwan Taiwan term.
 * @param string $mainland Mainland term.
 * @param bool   $requireSharedHan Literary tables need overlap.
 * @return bool
 */
function localePhraseConvertShouldKeepPair( $taiwan, $mainland, $requireSharedHan, $minLength ) {
	$taiwanLen   = mb_strlen( $taiwan, 'UTF-8' );
	$mainlandLen = mb_strlen( $mainland, 'UTF-8' );
	$minLength   = (int) $minLength;
	if ( $minLength < 2 ) {
		$minLength = 2;
	}
	if ( $taiwanLen < $minLength || $mainlandLen < $minLength ) {
		return false;
	}
	$shorter = min( $taiwanLen, $mainlandLen );
	$longer  = max( $taiwanLen, $mainlandLen );
	if ( $longer / $shorter > 2.0 ) {
		return false;
	}
	if ( abs( $taiwanLen - $mainlandLen ) > 3 ) {
		return false;
	}
	$generic = array(
		'碼'   => true,
		'通道' => true,
		'呼叫' => true,
		'聊天' => true,
		'標題' => true,
		'附件' => true,
		'細胞' => true,
		'橋'   => true,
		'擷取' => true,
		'正文' => true,
		'文本' => true,
		'出口' => true,
		'退出' => true,
		'歌曲' => true,
		'歌調' => true,
		'歌调' => true,
		'生活' => true,
		'生命' => true,
		'密度' => true,
		'緊度' => true,
		'紧度' => true,
	);
	if ( isset( $generic[ $taiwan ] ) || isset( $generic[ $mainland ] ) ) {
		return false;
	}
	if ( strpos( $mainland, '；' ) !== false || strpos( $mainland, '、' ) !== false ) {
		return false;
	}
	if ( $requireSharedHan && localePhraseConvertSharedHanCount( $taiwan, $mainland ) < 1 ) {
		return false;
	}
	return true;
}

/**
 * @param mixed                 $list Pair list.
 * @param array<string, string> $map Output map.
 */
function localePhraseConvertMergePairMap( $list, &$map ) {
	if ( ! is_array( $list ) ) {
		return;
	}
	foreach ( $list as $entry ) {
		if ( is_array( $entry ) && isset( $entry[0], $entry[1] ) && is_string( $entry[0] ) && is_string( $entry[1] ) ) {
			if ( ! isset( $map[ $entry[0] ] ) ) {
				$map[ $entry[0] ] = $entry[1];
			}
		}
	}
}

/**
 * @param array<string, string> $map Phrase map.
 * @return array<int, array{0: string, 1: string}>
 */
function localePhraseConvertPairList( $map ) {
	$pairs = array();
	foreach ( $map as $source => $target ) {
		$pairs[] = array( $source, $target );
	}
	usort(
		$pairs,
		static function ( $left, $right ) {
			$lengthDiff = mb_strlen( $right[0], 'UTF-8' ) - mb_strlen( $left[0], 'UTF-8' );
			if ( $lengthDiff !== 0 ) {
				return $lengthDiff;
			}
			return strcmp( $left[0], $right[0] );
		}
	);
	return $pairs;
}

if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
	$pluginDir = dirname( __FILE__ );
	$ok        = localePhraseConvertCompileUserPhrases( $pluginDir . '/dicts', $pluginDir . '/user-phrases.js' );
	fwrite( STDOUT, $ok ? "compiled user-phrases.js\n" : "compile failed\n" );
	exit( $ok ? 0 : 1 );
}
