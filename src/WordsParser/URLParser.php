<?php

namespace UZTranslit\WordsParser;

class URLParser
{
	public static $query_trans = [
        '%2F' => '/',
        '%2f' => '/',
        '%5B' => '[',
        '%5b' => '[',
        '%5D' => ']',
        '%5d' => ']',
    ];

	private function __construct() {}

	public static function encode($s)
	{
		if (!is_string($s)) {
            return $s;
        }

		return strtr(rawurldecode($s), self::$query_trans);
	}

	public static function replace_arg($url, $arg, $value = null, $is_use_sid = false)
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if ($url === null) {
            return null;
        }

		static $tr_table = [
            '\['  => '(?:\[|%5[Bb])',
            '\]'  => '(?:\]|%5[Dd])',
            '%5B' => '(?:\[|%5[Bb])',
            '%5D' => '(?:\]|%5[Dd])',
            '%5b' => '(?:\[|%5[Bb])',
            '%5d' => '(?:\]|%5[Dd])',
        ];

		if (is_bool($arg)) {
            $args = [$arg => intval($value)];
        } elseif (is_scalar($arg)) {
            $args = [$arg => $value];
        } elseif (is_array($arg)) {
            $args = $arg;
        } else {
            return false;
        }

		$original_url = $url;
		$args[session_name()] = null;

		if ($is_use_sid && session_id() && self::is_current_host($url)) {
            $args[session_name()] = session_id();
        }

		$p = explode('#', $url, 2);
		$p[0] = explode('?', $p[0], 2);

		if (!isset($p[0][1])) {
            $p[0][1] = '';
        }

		foreach ($args as $arg => $value) {
			if (!assert('is_scalar($value) || is_null($value)')) {
                return false;
            }

			if (!preg_match('/^[^&=\x00-\x20\x7f]+$/sSX', $arg)) {
				trigger_error('Illegal characters found in arguments. See second parameter (' . gettype($arg) . ' type given)!', E_USER_WARNING);
				return $original_url;
			}

			$re_arg = strtr(preg_quote($arg, '/'), $tr_table);
			if (preg_match('/((?:&|^)' . $re_arg . '=)[^&\x00-\x20\x7f]*/sSX', $p[0][1], $m)) {
				$v = is_null($value) ? '' : $m[1] . rawurlencode($value);
				$p[0][1] = str_replace($m[0], $v, $p[0][1]);
				continue;
			}

			if (is_null($value)) {
                continue;
            }

			$p[0][1] .= '&' . $arg . '=' . rawurlencode($value);
		}

		$p[0][1] = trim($p[0][1], '&');
		if ($p[0][1] === '') {
            unset($p[0][1]);
        }

		$p[0] = implode('?', $p[0]);
		return implode('#', $p);
	}

	public static function search_phrase($url, &$host = null)
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if (!is_string($url)) {
            return false;
        }

		$host = null;
		if (!$url) {
            return false;
        }

		$a = @parse_url($url);

		if (empty($a['host'])) {
            return false;
        }

		$host = $a['host'];

		if (empty($a['query'])) {
            return false;
        }

		$params = [
            'yandex.ru'     => 'text',
            'www.yandex.ru' => 'text',
            'yaca.yandex.ru'        => 'text',
            'search.yaca.yandex.ru' => 'text',
            'images.yandex.ru' => 'text',
            'go.mail.ru' => 'q',
            'nova.rambler.ru' => 'query',
            'google.ru'       => 'q',
            'google.com'      => 'q',
            'www.google.ru'   => 'q',
            'www.google.com'  => 'q',
            'search.yahoo.com' => 'p',
            'bing.com'     => 'q',
            'www.bing.com' => 'q',
            'sm.aport.ru' => 'r',
            'nigma.ru'     => 's',
            'www.nigma.ru' => 's',
            'www.daemon-search.com' => 'q',
        ];

		if (!array_key_exists($host, $params)) {
            return false;
        }

		$param = $params[$host];
		parse_str($a['query'], $query);
		$s = @$query[$param];

		if (!is_string($s)) {
            return false;
        }

		if (!UTF8::is_utf8($s)) {
			$s = UTF8::convert_from($s, 'cp1251');
			if (!is_string($s) || !UTF8::is_utf8($s)) {
                return false;
            }
		}

		return $s;
	}

	public static function build($parsed)
	{
		if (isset($parsed['scheme'])) {
            $url = $parsed['scheme'] . (strtolower($parsed['scheme']) === 'mailto' ? ':' : '://');
        } else {
		    $url = '';
        }

		if (isset($parsed['pass'])) {
            $url .= (isset($parsed['user']) ? $parsed['user'] : '') . ':' . $parsed['pass'] . '@';
        } elseif (isset($parsed['user'])) {
            $url .= $parsed['user'] . '@';
        }

		if (!isset($parsed['query'])) {
            $parsed['query'] = '';
        } elseif (is_array($parsed['query'])) {
            $parsed['query'] = strtr(http_build_query($parsed['query']), self::$query_trans);
        }

		if (isset($parsed['host'])) {
            $url .= $parsed['host'];
        }

		if (isset($parsed['port']) && isset($parsed['host'])) {
            $url .= ':' . $parsed['port'];
        }

		if (isset($parsed['path'])) {
            $url .= $parsed['path'];
        }

		if (strlen($parsed['query']) > 0) {
            $url .= '?' . $parsed['query'];
        }

		if (isset($parsed['fragment'])) {
            $url .= '#' . $parsed['fragment'];
        }

		return $url;
	}

	public static function is_current_host($url, $is_check_scheme = true, $is_check_port = true)
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if (!is_array($url)) {
			$url = @parse_url($url);
			if (!is_array($url)) {
			    return false;
            }
		}

		$is_current_host = ! array_key_exists('scheme', $url)
			|| ( (! $is_check_scheme || strtolower($url['scheme']) === strtolower(substr($_SERVER['SERVER_PROTOCOL'], 0, strlen($url['scheme']))))
				&& (
				! array_key_exists('host', $url)
					|| (
					strtolower($url['host']) === strtolower($_SERVER['HTTP_HOST'])
						&& (! array_key_exists('port', $url) || ! $is_check_port || $url['port'] === $_SERVER['SERVER_PORT'])
				)
			)
		);

		return $is_current_host;
	}

	public static function check($url, $schemes = null)
	{
		if (!ReflectionTypeHint::isValid()) {
		    return false;
        }

		if (!is_array($url)) {
			$url = @parse_url($url);
			if (!is_array($url)) {
			    return false;
            }
		}

		if (array_key_exists('scheme', $url)) {
			$url['scheme'] = strtolower($url['scheme']);
			if ($schemes && ! in_array($url['scheme'], $schemes)) {
                return false;
            } elseif (getservbyname($url['scheme'], 'tcp') === false) {
                return false;
            }
		}

		if (array_key_exists('host', $url) && ! preg_match(RE::HOST, $url['host'])) {
            return false;
        }

		if (array_key_exists('path', $url) && ! preg_match(RE::PATH, $url['path'])) {
            return false;
        }

		if (array_key_exists('query', $url) && ! preg_match(RE::QUERY, '?' . $url['query'])) {
            return false;
        }

		if (array_key_exists('fragment', $url) && ! preg_match(RE::FRAGMENT, '#' . $url['fragment'])) {
            return false;
        }

		return true;
	}

	public static function exists($url, $timeout = 30)
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		$urls = (array) $url;
		$mh = curl_multi_init();
		if ($mh === false) {
            return false;
        }

		$handles = [];
		foreach ($urls as $i => $url) {
			$options = [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_AUTOREFERER    => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => $timeout,  //The maximum number of seconds to allow cURL functions to execute.
                CURLOPT_CONNECTTIMEOUT => 0,         //The number of seconds to wait while trying to connect. Use 0 to wait indefinitely.
                CURLOPT_HEADER         => true,
                CURLOPT_NOBODY         => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            ];
			$handles[$i] = curl_init();

			if ($handles[$i] === false) {
                return false;
            }

            if (!curl_setopt_array($handles[$i], $options)) {
                return false;
            }

			if (curl_multi_add_handle($mh, $handles[$i]) !== 0) {
                return false;
            }
		}

		$running = null;
		do {
			curl_multi_exec($mh, $running);
			usleep(1000000 * 0.01);
		} while ($running > 0);

		$return = [];
		foreach ($urls as $i => $url) {
			$info = curl_getinfo($handles[$i]);
			$return[$url] = $info['http_code'];
		}

		foreach ($urls as $i => $url) {
            curl_multi_remove_handle($mh, $handles[$i]);
        }

		curl_multi_close($mh);
		return $return;
	}
}