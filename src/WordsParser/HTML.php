<?php

namespace UZTranslit\WordsParser;

class HTML
{
	public static $re_attrs = '(?![a-zA-Z\d])  #statement, which follows after a tag
                               #correct attributes
                               (?>
                                   [^>"\'`]++
                                 | (?<=[\=\x00-\x20\x7f]|\xc2\xa0) "[^"]*+"
                                 | (?<=[\=\x00-\x20\x7f]|\xc2\xa0) \'[^\']*+\'
                                 | (?<=[\=\x00-\x20\x7f]|\xc2\xa0) `[^`]*+`
                               )*+
                               #incorrect attributes
                               [^>]*+';

	public static $url_tags = [
        'body'   => 'background',
        'table'  => 'background',
        'tr'     => 'background',
        'td'     => 'background',
        'img'    => 'src|longdesc|alt',
        'frame'  => 'src|longdesc',
        'iframe' => 'src|longdesc',
        'input'  => 'src|alt',
        'script' => 'src',
        'a'      => 'href|title|rel|target',
        'area'   => 'href|alt|rel|target',
        'link'   => 'href|title|rel',
        'base'   => 'href',
        'object' => 'classid|codebase|data|usemap',
        'applet' => 'codebase|alt',  #HTML-4.01 deprecated tag
        'form'   => 'action',
        'q'      => 'cite',
        'ins'    => 'cite',
        'del'    => 'cite',
        'blockquote' => 'cite',
        'head'   => 'profile',
    ];

	private static $_safe_tags;
	private static $_safe_attrs;
	private static $_safe_attr_links;
	private static $_safe_protocols;
	private static $_normalize_links;
	private static $_subdomains_map = [];
	private function __construct() {}

	public static function init(array $subdomains_map = null)
	{
		if ($subdomains_map) {
            self::$_subdomains_map = $subdomains_map;
        }
	}

	public static function attrs($attrs, $delim = ', ', $is_null_value_check = false)
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if (is_null($attrs)) {
            return null;
        }

		$a = [];
		foreach ($attrs as $attr => $value) {
			if ($is_null_value_check && is_null($value)) {
                return null;
            }

			$attr = strtolower($attr);
			if (is_bool($value)) {
				if ($value) {
                    $a[] = $attr . '="' . htmlspecialchars($attr) . '"';
                }
				continue;
			}

			if (is_array($value)) {
				if ($attr === 'class') {
                    $delim = ' ';
                } elseif ($attr === 'style' || preg_match('/^on[a-z]+/siSX', $attr)) {
                    $delim = ';';
                } elseif (array_key_exists($attr, self::_url_attrs()) && strpos('alt|title|target|rel', $attr) === false) {
                    $value = URLParser::build($value);
                }

				if (is_array($value)) {
                    $value = implode($delim, $value);
                }
			}

			if (is_scalar($value)) {
                $a[] = $attr . '="' . htmlspecialchars($value) . '"';
            }
		}

		return implode(' ', $a);
	}

	public function tag($name, $attrs = null, $is_null_value_check = true)
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		$attrs = self::attrs($attrs, ', ', $is_null_value_check);
		if (!is_string($attrs)) {
            return $attrs;
        }

		if (strlen($attrs) > 0) {
            $attrs = ' ' . $attrs;
        }

		return '<' . strtolower($name) . $attrs . ' />';
	}

	public static function url($s, $is_html_quote = true)
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if (is_null($s)) {
            return $s;
        }

		if (is_array($s)) {
            $s = URLParser::build($s);
        }

		if ($s === false) {
            return false;
        }

		return $is_html_quote ? htmlspecialchars($s) : $s;
	}

	public static function src($filename, $is_html_quote = true)
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if (is_null($filename)) {
            return $filename;
        }

		$dir = dirname($_SERVER['SCRIPT_FILENAME']);
		$www_dir = substr($dir, strlen($_SERVER['DOCUMENT_ROOT']));

		$query_string = '';
		if (substr($filename, 0, 2) === '//') {
            $s = $www_dir . substr_replace($filename, '/' . $_REQUEST['lang'] . '/', 0, 2);
        } elseif (strpos($filename, '://') !== false && preg_match('~^[a-z][-a-z\d_]{2,19}+(?<![-_]):\/\/~sSX', $filename)) {
            $s = $filename;
        } elseif (strpos($filename, '?') === false &&
				pathinfo($filename, PATHINFO_EXTENSION) &&
				!preg_match('~\d\.\d~sSX', $filename)) {
			$mtime = file_exists($dir . $filename) ? @filemtime($dir . $filename) : null;
			$s = $www_dir . $filename . $query_string = '?' . intval($mtime);
			if (!$mtime) {
                $s .= '#404';
            }
		} else {
            $s = $www_dir . rtrim($filename, '?') . $query_string;
        }

		return $is_html_quote ? htmlspecialchars($s) : $s;
	}

	public static function quote($s, $delim = null)
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if (is_null($s)) {
            return $s;
        }

		if (is_array($s)) {
			if ($delim === null) {
				trigger_error('If 1-st parameter an array, a string type expected in 2-nd paramater, ' . gettype($delim) . ' given', E_USER_WARNING);
				return false;
			}
			$s = implode($delim, $s);
		}

		return htmlspecialchars($s);
	}

	public static function unquote($s, $quote_style = ENT_COMPAT)
	{
		if (!ReflectionTypeHint::isValid()) return false;
		if (is_null($s)) return $s;
		return htmlspecialchars_decode($s, $quote_style);
	}

    /**
     * @param string|array|null  $s
     * @param array|null $allowable_tags
     * @param bool $is_format_spaces
     * @param array $pair_tags
     * @param array $para_tags
     * @return  string|bool|null
     */
	public static function strip_tags($s,
                                      $allowable_tags = null,
                                      $is_format_spaces = true,
                                      $pair_tags = [
                                          'script', 'style', 'map', 'iframe',
                                          'frameset', 'object', 'applet',
                                          'comment', 'button', 'textarea', 'select'],
                                      $para_tags = [
                                          #Paragraph boundaries are inserted at every block-level HTML tag. Namely, those are (as taken from HTML 4 standard)
                                          'address', 'blockquote', 'caption',
                                          'center', 'dd', 'div', 'dl', 'dt',
                                          'h1', 'h2', 'h3', 'h4', 'h5', 'li',
                                          'menu', 'ol', 'p', 'pre', 'table',
                                          'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
                                          #Extended
                                          'form', 'title'
                                      ]) {

		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if (is_null($s)) {
            return $s;
        }

		static $_callback_type  = false;
		static $_allowable_tags = [];
		static $_para_tags      = [];

		if (is_array($s)) {
			if ($_callback_type === 'strip_tags') {
				$tag = strtolower($s[1]);
				if ($_allowable_tags) {
					if (array_key_exists($tag, $_allowable_tags)) {
                        return $s[0];
                    }

					if (array_key_exists('<' . $tag . '>', $_allowable_tags)) {
						if (substr($s[0], 0, 2) === '</') return '</' . $tag . '>';
						if (substr($s[0], -2) === '/>')   return '<' . $tag . ' />';
						return '<' . $tag . '>';
					}
				}

				if ($tag === 'br') {
                    return "\r\n";
                }

				if ($_para_tags && array_key_exists($tag, $_para_tags)) {
                    return "\r\n\r\n";
                }

				return '';
			}

			trigger_error('Unknown callback type "' . $_callback_type . '"!', E_USER_ERROR);
		}

		if (($pos = strpos($s, '<')) === false || strpos($s, '>', $pos) === false) {
			return $s;
		}

		$length = strlen($s);
		$re_tags = '~  <[/!]?+
                       (
                           [a-zA-Z][a-zA-Z\d]*+
                           (?>:[a-zA-Z][a-zA-Z\d]*+)?
                       ) #1
                       ' . self::$re_attrs . '
                       >
                    ~sxSX';

		$patterns = [
            '/<([\?\%]) .*? \\1>/sxSX',
            '/<\!\[CDATA\[ .*? \]\]>/sxSX',
            '/<\!--.*?-->/sSX',
            '/ <\! (?:--)?+
                   \[
                   (?> [^\]"\']+ | "[^"]*" | \'[^\']*\' )*
                   \]
                   (?:--)?+
               >
             /sxSX',
        ];

		if ($pair_tags) {
			foreach ($pair_tags as $k => $v) {
                $pair_tags[$k] = preg_quote($v, '/');
            }

			$patterns[] = '/ <((?i:' . implode('|', $pair_tags) . '))' . self::$re_attrs . '(?<!\/)>
                               .*?
                             <\/(?i:\\1)' . self::$re_attrs . '>
                           /sxSX';

			ini_set('pcre.backtrack_limit', 1000000);
		}

		$i = 0;
		$max = 99;
		while ($i < $max) {
			$s2 = preg_replace($patterns, '', $s);
			if ((function_exists('preg_last_error') && preg_last_error() !== PREG_NO_ERROR) || $s2 === false) {
				$i = 999;
				break;
			}

			if ($i == 0) {
				$is_html = ($s2 != $s || preg_match($re_tags, $s2));
				if (function_exists('preg_last_error') && preg_last_error() !== PREG_NO_ERROR) {
					$i = 999;
					break;
				}

				if ($is_html) {
					if ($is_format_spaces) {
						if (version_compare(PHP_VERSION, '5.2.0', '>=')) {
							$s2 = preg_replace('/  [\x09\x0a\x0c\x0d]++
                                                 | <((?i:pre|textarea))' . self::$re_attrs . '(?<!\/)> #1
                                                   .+?
                                                   <\/(?i:\\1)' . self::$re_attrs . '>
                                                   \K
                                                /sxSX', ' ', $s2);
						} else {
							$s2 = preg_replace('/  [\x09\x0a\x0c\x0d]++
                                                 | ( #1
                                                     <((?i:pre|textarea))' . self::$re_attrs . '(?<!\/)> #2
                                                     .+?
                                                     <\/(?i:\\2)' . self::$re_attrs . '>
                                                   )
                                                /sxSX', '$1 ', $s2);
						}
						if ((function_exists('preg_last_error') && preg_last_error() !== PREG_NO_ERROR) || $s2 === false) {
							$i = 999;
							break;
						}
					}

					if ($allowable_tags) {
                        $_allowable_tags = array_flip($allowable_tags);
                    }

					if ($para_tags) {
                        $_para_tags = array_flip($para_tags);
                    }
				}
			}

			if ($is_html) {
				$_callback_type = 'strip_tags';
				$s2 = preg_replace_callback($re_tags, ['self', __FUNCTION__], $s2);
				$_callback_type = false;
				if ((function_exists('preg_last_error') && preg_last_error() !== PREG_NO_ERROR) || $s2 === false) {
					$i = 999;
					break;
				}
			}

			if ($s === $s2) break;
			$s = $s2; $i++;
		}

		if ($i >= $max) {
            $s = strip_tags($s);
        }

		if ($is_format_spaces && strlen($s) !== $length) {
			#remove a duplicate spaces
			$s = preg_replace('/\x20\x20++/sSX', ' ', trim($s));
			#remove a spaces before and after new lines
			$s = str_replace(["\r\n\x20", "\x20\r\n"], "\r\n", $s);
			#replace 3 and more new lines to 2 new lines
			$s = preg_replace('/[\r\n]{3,}+/sSX', "\r\n\r\n", $s);
		}

		return $s;
	}

	public static function is_html($s, array $no_html_tags = null)
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

        if ($s === null) {
            return null;
        }

		$regexp = '~(?> <[a-zA-Z][a-zA-Z\d]*+  {no_html_tags_open_re}' . self::$re_attrs . '> # open pair tag / self-closed tag
					  | </[a-zA-Z][a-zA-Z\d]*+ {no_html_tags_close_re}>                       # closed tag
					  | <!-- .*? -->                                       # comment
					  | <![A-Z] .*? >                                      # DOCTYPE, ENTITY
					  | <\!\[CDATA\[ .*? \]\]>                             # CDATA
					  | <\? .*? \?>                                        # instructions
					  | <\% .*? \%>                                        # instructions
					  # MS Word, IE (Internet Explorer) condition tags
					  | <! (?:--)?+
						   \[
						   (?> [^\]"\'`]+
							 | "[^"]*"
							 | \'[^\']*\'
							 | `[^`]*`
						   )*+
						   \]
						   (?:--)?+
						>
					)
				   ~sxSX';
		$regexp = str_replace('{no_html_tags_open_re}',  $no_html_tags ? '(?<!<(?i:' . implode('|', $no_html_tags) . '))' : '', $regexp);
		$regexp = str_replace('{no_html_tags_close_re}', $no_html_tags ? '(?<!/(?i:' . implode('|', $no_html_tags) . '))' : '', $regexp);
		return (bool) preg_match($regexp, $s);
	}

	public static function paragraph($s, $is_single = false, &$is_html = false, $no_html_tags = ['notypo'])
	{
		if (! ReflectionTypeHint::isValid()) {
            return false;
        }

		if (is_null($s)) {
            return $s;
        }

		$is_html = self::is_html($s, $no_html_tags);
		if (!$is_html) {
			$a = preg_split('/(\r\n|[\r\n])(?>[\x20\t]|\\1)+/sSX', trim($s), -1, PREG_SPLIT_NO_EMPTY);
			$a = array_map('trim', $a);
			$a = preg_replace('/[\r\n]++/sSX', "<br />\r\n", $a);
			if (count($a) > intval(!(bool)$is_single)) {
                $s = '<p>' . implode("</p>\r\n\r\n<p>", $a) . '</p>';
            } else {
                $s = implode('', $a);
            }
			$s = preg_replace('/\x20\x20++/sSX', ' ', $s);
		}

		return $s;
	}

	public static function words_highlight($s, $words = null, $is_case_sensitive = false, $tpl = '<span class="highlight">%s</span>')
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if (is_null($s)) {
            return $s;
        }

		if (!strlen($s) || !$words) {
            return $s;
        }

		$s2 = UTF8::lowercase($s);
		foreach ($words as $k => $word) {
			$word = UTF8::lowercase(trim($word, "\x00..\x20\x7f*"));
			if ($word == '' || strpos($s2, $word) === false) {
                unset($words[$k]);
            }
		}

		if (!$words) {
            return $s;
        }

		static $func_cache = [];
		$cache_id = md5(serialize([$words, $is_case_sensitive, $tpl]));
		if (! array_key_exists($cache_id, $func_cache)) {
			$re_words = [];
			foreach ($words as $word) {
				$is_mask = (substr($word, -1) === '*');
				if ($is_mask) {
                    $word = rtrim($word, '*');
                }

				$is_digit = ctype_digit($word);
				$re_word = preg_quote($word, '~');
				if (!$is_case_sensitive && ! $is_digit) {
					if (UTF8::is_ascii($word)) {
                        $re_word = '(?i:' . $re_word . ')';
                    } else {
						$lc = UTF8::str_split(UTF8::lowercase($re_word));
						$uc = UTF8::str_split(UTF8::uppercase($re_word));
						$re_word = [];
						foreach ($lc as $i => $tmp){
							$re_word[] = '[' . $lc[$i] . $uc[$i] . ']';
						}
						$re_word = implode('', $re_word);
					}
				}

				if ($is_digit) {
                    $append = $is_mask ? '\d*+' : '(?!\d)';
                } else {
                    $append = $is_mask ? '\p{L}*+' : '(?!\p{L})';
                }

				$re_words[$is_digit ? 'digits' : 'words'][] = $re_word . $append;
			}

			if (array_key_exists('words', $re_words) && $re_words['words']) {
				$re_words['words'] = '(?<!\p{L})  #просмотр назад (\b не подходит и работает медленнее)
                                      (?:' . implode(PHP_EOL . '| ', $re_words['words']) . ')
                                      ';
			}
			if (array_key_exists('digits', $re_words) && $re_words['digits']) {
				$re_words['digits'] = '(?<!\d)  #просмотр назад (\b не подходит и работает медленнее)
                                       (?:' . implode(PHP_EOL . '| ', $re_words['digits']) . ')
                                       ';
			}

			$func_cache[$cache_id] = '~(?>  #встроенный PHP, Perl, ASP код
											<([\?\%]) .*? \\1>
											\K

											#блоки CDATA
                                         |  <\!\[CDATA\[ .*? \]\]>
											\K

											#MS Word тэги типа "<![if! vml]>...<![endif]>",
											#условное выполнение кода для IE типа "<!--[if lt IE 7]>...<![endif]-->":
                                         |  <\! (?>--)?
												\[
												(?> [^\]"\']+ | "[^"]*" | \'[^\']*\' )*
												\]
												(?>--)?
											>
											\K

											#комментарии
                                         |  <\!-- .*? -->
											\K

											#парные тэги вместе с содержимым
                                         |  <((?i:noindex|script|style|comment|button|map|iframe|frameset|object|applet))' . self::$re_attrs . '(?<!/)>
												.*?
											</(?i:\\2)>
											\K

											#парные и непарные тэги
                                         |  <[/\!]?+[a-zA-Z][a-zA-Z\d]*+' . self::$re_attrs . '>
											\K

											#html сущности (&lt; &gt; &amp;) (+ корректно обрабатываем код типа &amp;amp;nbsp;)
                                         |  &(?> [a-zA-Z][a-zA-Z\d]++
												| \#(?>		\d{1,4}+
														|	x[\da-fA-F]{2,4}+
													)
                                            );
											\K

										 | ' . implode(PHP_EOL . '| ', $re_words) . '
                                       )
                                      ~suxSX';
		}

		$s = preg_replace_callback($func_cache[$cache_id],
            function ($m) use ($tpl) { return ($m[0] !== '') ? sprintf($tpl, $m[0]) : $m[0]; }, $s);

		return $s;
	}

	public static function watermark($s)
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if (is_null($s)) {
            return $s;
        }

		return preg_replace_callback('~</(?: p>  (?<!<p></p>)   (?![\x00-\x20]*+</(?:td|li)>)
                                           | td> (?<!<td></td>) [\x00-\x20]*+ </tr>
                                           | li> (?<!<li></li>) [\x00-\x20]*+ </[ou]l>
                                         )
                                      ~sixSX', ['self', '_watermark'], $s);
	}

	private static function _watermark($m)
	{
		static $i = 0, $url = null, $hash = null;
		if ($url === null) {
			$url  = 'http://' . $_SERVER['SERVER_NAME'] . URLParser::replace_arg($_SERVER['REQUEST_URI'], [], $is_use_sid = false);
			$hash = base_convert(md5($url), 16, 36);
		}
		return '<noindex><span class="fake invisible" style="position:absolute;display:block;width:0;height:0;text-indent:-2989px;font:normal 0 sans-serif;opacity:0.01;filter:alpha(opacity=1);watermark:' . $hash . ',' . dechex(++$i) . '">&nbsp;&copy;&nbsp;' . $url . '</span></noindex>' . $m[0];
	}

	public static function normalize_tags($s,
		&$invalid_tags = null,
		&$deleted_tags = null,
		$tags = [
            'html', 'head', 'body',
            'title', 'h[1-6]',
            'span', 'div',
            'form', 'textarea', 'button', 'option', 'label', 'select', #формы
            'strong', 'em', 'big', 'small', 'sub', 'sup', 'tt',
            '[abius]', 'bdo', 'caption', 'del', 'ins',
            'script', 'noscript', 'style', 'map', 'applet', 'object',
            'table', 't[rhd]', #таблицы
            'nobr', 'noindex', 'wiki', 'notypo', 'comment',
        ]
	) {
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if (is_null($s)) {
            return $s;
        }

		static $_opened_tags  = [];
		static $_deleted_tags = [];

		if (is_array($s) && $invalid_tags === null) {
			if ($s[0] === '' || ! isset($s[2])) {
                return $s[0];
            }

            $t = substr($s[0], 0, 2);
            if ($t === '<?' || $t === '<%' || $t === '<!') {
                return $s[0];
            }

			$tag = strtolower($s[2]);
			if (! array_key_exists($tag, $_opened_tags)) $_opened_tags[$tag] = 0;
			$o =& $_opened_tags[$tag];
			if ($s[1] !== '/') {
				$o++;
				if ($o > 1) {
					if (!array_key_exists($tag, $_deleted_tags)) {
                        $_deleted_tags[$tag] = 0;
                    }
					$_deleted_tags[$tag]++;
					return '';
				}
			} else {
				$o--;
				if ($o > 0) {
					if (!array_key_exists($tag, $_deleted_tags)) {
                        $_deleted_tags[$tag] = 0;
                    }
					$_deleted_tags[$tag]++;
					return '';
				}
			}
			return $s[0];
		}

		$s = preg_replace_callback('~(?>
											<(/)?+                                  #1
												((?i:' . implode('|', $tags) . '))  #2
												(?(1)|' . self::$re_attrs . '(?<!/))
											>

											#встроенный PHP, Perl, ASP код
										|	<([?%]) .*? \\3>  #3
			
											#блоки CDATA
										|	<!\[CDATA\[ .*? \]\]>

											#MS Word тэги типа "<![if! vml]>...<![endif]>",
											#условное выполнение кода для IE типа "<!--[if lt IE 7]>...<![endif]-->"
										|	<! (?>--)?
												\[
												(?> [^\]"\']+ | "[^"]*" | \'[^\']*\' )*
												\]
												(?>--)?
											>

											#комментарии
										|	<!-- .*? -->
									)
								~sxSX', ['self', __FUNCTION__], $s);
		$invalid_tags = [];
		foreach ($_opened_tags as $tag => $count) {
            if ($count !== 0) {
                $invalid_tags[] = $tag;
            }
        }

		$deleted_tags = $_deleted_tags;
		$_opened_tags  = [];
		$_deleted_tags = [];

		return $s;
	}

	public static function is_xhtml($s,
									$is_strict = true,
									$strict_is_legacy_allow = true,
									$error_offset = null,
									$strict_pair_tags_extra  = ['nobr', 'notypo', 'wiki'],
									$strict_empty_tags_extra = ['typo', 'page'],
									$strict_attrs_extra      = [
										'md5',
										'time', 'speed', 'length', 'char_length', 'created', 'version',
                                    ]
	) {
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if (is_null($s)) {
            return $s;
        }

		if (($pos = strpos($s, '<')) === false || strpos($s, '>', $pos) === false) {
			return true;
		}

		if ($is_strict) {
			$re_attr_names = '
                             #Common (Core + I18N + Style + Events)
                               xml:space|class|id|title  #Core
                               |dir|xml:lang             #I18N
                               |style                    #Style
                               #|on[a-z]{3,30}+          #Events
                               #Events
                               |on(?:abort
                                    |activate
                                    |afterprint
                                    |afterupdate
                                    |beforeactivate
                                    |beforecopy
                                    |beforecut
                                    |beforedeactivate
                                    |beforeeditfocus
                                    |beforepaste
                                    |beforeprint
                                    |beforeunload
                                    |beforeupdate
                                    |blur
                                    |bounce
                                    |cellchange
                                    |change
                                    |click
                                    |contextmenu
                                    |controlselect
                                    |copy
                                    |cut
                                    |dataavailable
                                    |datasetchanged
                                    |datasetcomplete
                                    |dblclick
                                    |deactivate
                                    |drag
                                    |dragend
                                    |dragenter
                                    |dragleave
                                    |dragover
                                    |dragstart
                                    |drop
                                    |error
                                    |errorupdate
                                    |filterchange
                                    |finish
                                    |focus
                                    |focusin
                                    |focusout
                                    |help
                                    |keydown
                                    |keypress
                                    |keyup
                                    |layoutcomplete
                                    |load
                                    |losecapture
                                    |mousedown
                                    |mouseenter
                                    |mouseleave
                                    |mousemove
                                    |mouseout
                                    |mouseover
                                    |mouseup
                                    |mousewheel
                                    |move
                                    |moveend
                                    |movestart
                                    |paste
                                    |propertychange
                                    |readystatechange
                                    |reset
                                    |resize
                                    |resizeend
                                    |resizestart
                                    |rowenter
                                    |rowexit
                                    |rowsdelete
                                    |rowsinserted
                                    |scroll
                                    |select
                                    |selectionchange
                                    |selectstart
                                    |start
                                    |stop
                                    |submit
                                    |unload
                                   )
                             #Structure Module
                               |profile|version|xmlns
                             #Text Module
                               |cite
                             #Hypertext Module
                               |accesskey|charset|href|hreflang|rel|rev|tabindex|type|target  #<a>
                             #Applet Module
                               |alt|archive|code|codebase|height|object|width  #<applet>
                               |name|type|value|valuetype                      #<param>
                             #Edit Module
                               |cite|datetime  #<del>, <ins>
                             #Bi-directional Text Module
                               |dir|xml:lang
                             #Basic Forms Module
                               |accept|accept-charset|action|method|enctype                    #<form>
                               |accept|accesskey|alt|checked|disabled|maxlength|name|readonly|size|src|tabindex|type|value  #<input>
                               |disabled|multiple|name|size|tabindex                           #<select>
                               |disabled|label|selected|value                                  #<option>
                               |accesskey|cols|disabled|name|readonly|rows|tabindex            #<textarea>
                               |accesskey|disabled|name|tabindex|type|value                    #<button>
                               |accesskey|for                                                  #<label>
                               |accesskey                                                      #<legend>
                               |disabled|label                                                 #<optgroup>
                             #Tables Module
                               |border|cellpadding|cellspacing|frame|rules|summary|width           #<table>
                               |abbr|align|axis|char|charoff|colspan|headers|rowspan|scope|valign  #<td>, <th>
                               |align|char|charoff|valign                                          #<tr>
                               |align|char|charoff|span|valign|width                               #<col>, <colgroup>
                               |align|char|charoff|valign                                          #<tbody>, <thead>, <tfoot>
                             #Image Module
                               |alt|height|longdesc|src|width|usemap|ismap  #<img>
                             #Client-side Image Map Module
                               |accesskey|alt|coords|href|nohref|shape|tabindex  #<area>
                               |class|id|title                                   #<map>
                             #Object Module
                               |archive|classid|codebase|codetype|data|declare|height|name|standby|tabindex|type|width  #<object>
                               |id|name|type|value|valuetype                                                            #<param>
                             #Frames Module
                               |cols|rows                                                                   #<frameset>
                               |frameborder|longdesc|marginheight|marginwidth|noresize|scrolling|src  #<frame>
                             #Iframe Module
                               |frameborder|height|longdesc|marginheight|marginwidth|scrolling|src|width  #<iframe>
                             #Metainformation Module
                               |content|http-equiv|id|name|scheme  #<meta>
                             #Scripting Module
                               |charset|defer|id|src|type  #<script>
                             #Style Sheet Module
                               |id|media|title|type  #<style>
                             #Link Module
                               |charset|href|hreflang|media|rel|rev|type  #<link>
                             #Base Module
                               |href|id  #<base>
                             #Legacy Module
                               #This module is deprecated
                             ';

			$re_pair_tags = ' #Structure Module
                                body|head|html#|title
                              #Text Module
                                |abbr|acronym|address|blockquote|cite|code|dfn|div|em|h[1-6]|kbd|p|pre|q|samp|span|strong|var
                              #Hypertext Module
                                |a
                              #List Module
                                |dl|dt|dd|ol|ul|li
                              #Presentation Module
                                |b|big|i|small|sub|sup|tt
                              #Edit Module
                                |del|ins
                              #Bidirectional Text Module
                                |bdo
                              #Forms Module
                                |button|fieldset|form|label|legend|select|optgroup #|option|textarea
                              #Table Module
                                |caption|colgroup|table|tbody|td|tfoot|th|thead|tr
                              #Client-side Image Map Module
                                |map
                              #Object Module
                                |object
                              #Frames Module
                                |frameset|noframes
                              #Iframe Module
                                |iframe
                              #Scripting Module
                                |noscript #|script
                              #Stylesheet Module
                                #|style
                            ';
			$re_empty_tags = 'br|param|hr|input|col|img|area|frame|meta|link|base';

			if ($strict_attrs_extra) {
                $re_attr_names .= '|' . implode('|', $strict_attrs_extra);
            }

			if ($strict_pair_tags_extra) {
                $re_pair_tags  .= '|' . implode('|', $strict_pair_tags_extra);
            }

			if ($strict_empty_tags_extra) {
                $re_empty_tags .= '|' . implode('|', $strict_empty_tags_extra);
            }

			if ($strict_is_legacy_allow) {
				$re_attr_names .= '|color|face|id|size|compact|prompt|alink|background|bgcolor|link|text|vlink|clear|align|noshade|nowrap|width|height|border|hspace|vspace|type|value|start|language';
				$re_pair_tags  .= '|center|dir|font|menu|s|strike|u';
				$re_empty_tags .= '|basefont|isindex';
			}

			$re_attrs = '(?>
                           (?>[\x00-\x20\x7f]+|\xc2\xa0)++  #spaces
                           #(?:xml:)?+ [a-z]{3,30}+         #name
                           (?:' . $re_attr_names . ')       #name
                           =                                #equal
                           (?> "[^"<>]*+"                   #value in ""
                             | \'[^\'<>]*+\'                #value in \'\'
                           )
                         )*+
                         (?>[\x00-\x20\x7f]+|\xc2\xa0)*+';

			$re_html = '~
                         ^(?<main>
                             (?> # pair of tags (have any tags)
                                 <(?<tag1>' . $re_pair_tags . ')' . $re_attrs . '>
                                   (?&main)
                                 </\g{tag1}>

                                 #CDATA
                               | (?<cdata> <!\[CDATA\[ .*? \]\]>)

                                 # pair of tags (no have tags)
                               | <(?<tag2>script|style|option|textarea|title)' . $re_attrs . '>
                                   [^<>]*+
                                   (?: (?&cdata) )?+
                                   [^<>]*+
                                 </\g{tag2}>

                                 # self-closing tag
                               | <(?:' . $re_empty_tags . ')' . $re_attrs . '/>

                                 # non-tag stuff
                               | [^<>]++

                                 # comment
                               | <!-- .*? -->

                                 # DOCTYPE, ENTITY
                               | <![A-Z] .*? >

                                 # instructions (PHP, Perl, ASP)
                               | <\? .*? \?>
                               | <%  .*?  %>

                             )*+
                         )
                        ~sxSX';
		} else {
			$re_html = '~
                         ^(?<main>
                             (?> # pair of tags (have any tags)
                                 <(?<tag1>[a-zA-Z][a-zA-Z\d]*+) (?<!<script|<style|<option|<textarea|<title) ' . self::$re_attrs . ' (?<!/)>
                                   (?&main)
                                 </\g{tag1}>

                                 # pair of tags (no have tags)
                               | <(?<tag2>script|style|option|textarea|title) ' . self::$re_attrs . ' (?<!/)>
                                   .*?
                                 </\g{tag2}>

                                 # self-closing tag
                               | <[a-zA-Z][a-zA-Z\d]*+ ' . self::$re_attrs . ' (?<=/)>

                                 # non-tag stuff (part 1)
                               | [^<]++

                                 # non-tag stuff (part 2)
                               | (?! </?+[a-zA-Z]   # open/close tags
                                   | <!(?-i:[A-Z])  # DOCTYPE/ENTITY
                                   | <[\?%\[]       # instructions; MS Word, IE
                                   | <!--           # comments
                                 ).

                                 # comment
                               | <!-- .*? -->

                                 # DOCTYPE, ENTITY
                               | <!(?-i:[A-Z]) .*? >

                                 # instructions (PHP, Perl, ASP)
                               | <\? .*? \?>
                               | <%  .*?  %>

                                 # MS Word, IE (Internet Explorer) condition tags
                               | <! (?:--)?+
                                    \[
                                    (?> [^\]"\'`]+
                                      | "[^"]*"
                                      | \'[^\']*\'
                                      | `[^`]*`
                                    )*+
                                    \]
                                    (?:--)?+
                                 >

                             )*+
                         )
                        ~sxiSX';
		}

		if (! preg_match($re_html, $s, $m)) {
            return false;
        }

		if (strlen($s) === strlen($m[0])) {
            return true;
        }

		$error_offset = strlen($m[0]);
		return false;
	}

	public static function safe(
		$s,
		$tags       = ['p', 'a', 'b', 'strong', 'i', 'em', 'u', 's', 'br', 'wbr', 'ol', 'ul', 'li', 'tt', 'sup', 'sub', 'pre', 'code', 'img', 'nobr', 'font', 'blockquote', 'noindex'],
		$attrs      = ['class', /*'style',*/ 'align', 'target', 'title', 'href', 'src'/*, 'dynsrc', 'lowsrc'*/, 'border', 'alt', 'type', 'color'],
		$attr_links = ['action', 'background', 'codebase', 'dynsrc', 'lowsrc', 'href', 'src'],
		$protocols  = ['ed2k', 'file', 'ftp', 'gopher', 'http', 'https', 'irc', 'mailto', 'news', 'nntp', 'telnet', 'webcal', 'xmpp', 'callto']
	) {
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if (is_null($s)) {
            return $s;
        }

		if (($pos = strpos($s, '<') === false) || strpos($s, '>', $pos) === false) {
			return $s;
		}

		self::$_safe_tags       = array_flip($tags);
		self::$_safe_attrs      = array_flip($attrs);
		self::$_safe_attr_links = array_flip($attr_links);
		self::$_safe_protocols  = array_flip($protocols);

		$rules = [
            '/<([\?\%]).*?\\1>/sSX',      #встроенный PHP, Perl, ASP код
            #'/<\!\[CDATA\[.*?\]\]>/sSX', #блоки CDATA (закомментировано, см. нижеследующее рег. выражение)
            '/<\!\[[a-zA-Z].*?\]>/sSX',   #MS Word тэги типа <![if! vml]><![endif]>
            '/<\!--.*?-->/sSX',           #комментарии
            #парные тэги вместе с содержимым:
            '/ <((?i:title|script|style|comment|button|map|iframe|frameset|object|applet))' . self::$re_attrs . '(?<!\/)>
                 .*?
               <\/(?i:\\1)>
             /sxSX',
        ];
		$s = preg_replace($rules, '', $s);
		$s = self::entity_decode($s);
		$s = preg_replace_callback('/<
                                      ([\/\!]?+)                     #1 открывающий или закрывающий тэг, !DOCTYPE
                                      ([a-zA-Z][a-zA-Z\d]*+)         #2 тэг
                                      (' . self::$re_attrs . ')  #3 атрибуты
                                     >
                                    /sxSX', ['self', '_safe_tags_callback'], $s);

		self::$_safe_tags = self::$_safe_attrs = self::$_safe_attr_links = self::$_safe_protocols = null;
		return trim($s, "\x00..\x20\x7f");
	}

	private static function _safe_tags_callback(array $m)
	{
		$tag = strtolower($m[2]);
		if (!array_key_exists($tag, self::$_safe_tags)) {
            return '';
        }

		preg_match_all('/(?<=[\x20\r\n\t]|\xc2\xa0)     #пробельные символы (д.б. обязательно)
                         ([a-zA-Z][a-zA-Z\d\:\-]*+)     #1 название атрибута
                         (?>[\x20\r\n\t]++|\xc2\xa0)*+  #пробельные символы (необязательно)
                         (?>\=
                           (?>[\x20\r\n\t]++|\xc2\xa0)*  #пробельные символы (необязательно)
                           (   "[^"]*+"
                             | \'[^\']*+\'
                             | `[^`]*+`
                             | [^\x20\r\n\t]*+  #значение атрибута без кавычек и пробельных символов
                           )                    #2 значение атрибута
                         )?
                        /sxSX', $m[3], $matches, PREG_SET_ORDER);

		$attrs = [];
		foreach ($matches as $i => $a) {
			if (!array_key_exists(2, $a)) {
                continue;
            }
			list (, $attr, $value) = $a;
			$attr = strtolower($attr);

			if (! array_key_exists($attr, self::$_safe_attrs) || array_key_exists($attr, $attrs)) {
                continue;
            }

			if (strpos('"\'`', substr($value, 0, 1)) !== false) {
                $value = trim(substr($value, 1, -1), "\x00..\x20\x7f");
            }

			if (strpos($value, '&') !== false) {
                $value = htmlspecialchars_decode($value, ENT_QUOTES);
            }

			if (array_key_exists($attr, self::$_safe_attr_links) &&
				preg_match('/^([a-zA-Z\d]++)[\x20\r\n\t]*+\:/sSX', $value, $p) &&
				! array_key_exists(strtolower($p[1]), self::$_safe_protocols)
			) {
                continue;
            }

			$attrs[$attr] = ' ' . $attr . '="' . htmlspecialchars($value) . '"';
		}
		return '<' . $m[1] . $tag . implode('', $attrs) . (substr($m[0], -2, 2) === '/>' ? ' />' : '>');
	}

	public static function normalize_links(
		$s,
		$our_links_re = null,
		$path_search  = null,
		$path_replace = null,
		$host_trans   = null,
		$is_add_host  = false,
		$is_add_extra = false,
		&$valid_links  = null,
		&$broken_links = null)
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		if (is_null($s)) {
            return $s;
        }

		$valid_links = [];
		if (($pos = strpos($s, '<')) === false || strpos($s, '>', $pos) === false) {
            return $s;
        }

		if ($path_search !== null && ! preg_match('~^/(?:[^\x00-\x20\x7f/\\\\]++/)*+$~sSX', $path_search)) {
			trigger_error('Invalid format in 2-nd parameter', E_USER_WARNING);
			return false;
		}

		if ($path_replace !== null && ! preg_match('~^[^\x00-\x20\x7f]++$~sSX', $path_replace)) {
			trigger_error('Invalid format in 3-rd parameter', E_USER_WARNING);
			return false;
		}

		self::$_normalize_links = [
            'our_links_re' => $our_links_re,
            'path_search'  => $path_search,
            'path_replace' => $path_replace,
            'host_trans'   => $host_trans,
            'is_add_host'  => $is_add_host,
            'is_add_extra' => $is_add_extra,
            'valid_links'  => [],
            'broken_links' => [],
        ];

		$re_tags = implode('|', array_keys(self::$url_tags));
		$s = preg_replace_callback('~<((?i:' . $re_tags . '))                  #1 тэг
                                      (?!(?>[\x00-\x20\x7f]+|\xc2\xa0)*+/?+>)  #атрибуты должны существовать!
                                      (' . self::$re_attrs . ')                #2 атрибуты
                                     >
                                    ~sxSX', ['self', '_normalize_links_tags'], $s);
		$valid_links  = self::$_normalize_links['valid_links'];
		$broken_links = self::$_normalize_links['broken_links'];
		self::$_normalize_links = null;
		return $s;
	}

	private static function _normalize_links_tags($m)
	{
		self::$_normalize_links['tag'] = strtolower($m[1]);
		unset(self::$_normalize_links['attr.title'],
			self::$_normalize_links['attr.link'],
			self::$_normalize_links['attr.rel']);

		$m[2] = preg_replace_callback('~(?<![a-zA-Z\d])                  #предыдущий символ
                                        ((?>[\x00-\x20\x7f]+|\xc2\xa0)*+) #1 пробелы (необязательно)
                                        ((?i:' . implode('|', self::_url_attrs()) . '))   #2 атрибут
                                        (?>[\x00-\x20\x7f]+|\xc2\xa0)*+  #пробелы (необязательно)
                                        =
                                        (?>[\x00-\x20\x7f]+|\xc2\xa0)*+  #пробелы (необязательно)
                                        (   "[^"]+"
                                          | \'[^\']+\'
                                          | `[^`]+`
                                          | ([^"\'`\x00-\x20\x7f]++)  #4 значение атрибута без кавычек и пробельных символов
                                        ) #3 значение атрибута (не пустое!)
                                       ~sxSX', ['self', '_normalize_links_attrs'], $m[2]);

		if (isset(self::$_normalize_links['attr.link'])) {
			if (! array_key_exists(self::$_normalize_links['attr.link'], self::$_normalize_links['valid_links'])) {
				self::$_normalize_links['valid_links'][self::$_normalize_links['attr.link']] = @self::$_normalize_links['attr.title'];
			}
			if (self::$_normalize_links['is_add_extra']) {
				$rels = [];
				if (in_array(self::$_normalize_links['tag'], ['a', 'area', 'link'])
					&& !URLParser::is_current_host(self::$_normalize_links['attr.link'], $is_check_scheme = false, $is_check_port = false)
					&& (self::$_normalize_links['our_links_re'] === null || ! preg_match(self::$_normalize_links['our_links_re'], self::$_normalize_links['attr.link']))
				) {
					$rels[] = 'nofollow';
					if (self::$_normalize_links['tag'] !== 'link') {
                        $m[2] .= ' target="_blank"';
                    }
				}
				if (isset(self::$_normalize_links['attr.rel'])) {
                    $rels[] = trim(str_replace('nofollow', '', self::$_normalize_links['attr.rel']));
                }
				if ($rels) {
                    $m[2] .= ' rel="' . htmlspecialchars(trim(implode(' ', $rels))) . '"';
                }
			}
		}

		$tag = '<' . $m[1] . $m[2] . '>';
		return $tag;
	}

	private static function _normalize_links_attrs(array $m)
	{
		$attr = strtolower($m[2]);
		if (strpos(self::$url_tags[self::$_normalize_links['tag']], $attr) === false) {
            return $m[0];
        }

		if ($m[1] === '') {
            $m[1] = ' ';
        }

		$value = trim(isset($m[4]) ? $m[3] : substr($m[3], 1, -1), "\x00..\x20\x7f");
		$value = self::entity_decode($value, $is_htmlspecialchars = true);

		if ($attr === 'rel' || $attr === 'target') {
			if (! self::$_normalize_links['is_add_extra']) return $m[1] . $m[2] . '="' . htmlspecialchars($value) . '"';
			self::$_normalize_links['attr.' . $attr] = $value;
			return '';
		}

		if (($attr === 'title' || $attr === 'alt') && !array_key_exists('attr.title', self::$_normalize_links)) {
            self::$_normalize_links['attr.title'] = $value;
        } else {
			$url = $value;
			$url = preg_replace('~^[a-z][-a-z\d_]{2,19}+(?<![-_]):/(?!/)~siSX', '$0/', $url);
			$url = preg_replace('~^htt?+p:?+//~siSX', 'http://', $url);
			$url = @parse_url($url);
			$url_parsed = self::_normalize_links_parse($url, $is_fragment_only);

			if (is_array($url_parsed)) {
                $url_parsed = URLParser::build($url_parsed);
            }

			if (is_string($url_parsed)) {
				$value = $url_parsed;
				if (! $is_fragment_only) {
					if (array_key_exists('fragment', $url)) {
                        $url_parsed = substr($url_parsed, 0, -1 * strlen('#' . $url['fragment']));
                    }
					self::$_normalize_links['attr.link'] = $url_parsed;
				}
			} else {
				if (!array_key_exists($value, self::$_normalize_links['broken_links'])) {
                    self::$_normalize_links['broken_links'][$value] = 1;
                } else {
                    self::$_normalize_links['broken_links'][$value]++;
                }
			}
		}

		return $m[1] . $m[2] . '="' . htmlspecialchars($value) . '"';
	}

	private static function _normalize_links_parse($url, &$is_fragment_only = false)
	{
		static $servbyname = [];

		if ($url === false) {
            return false;
        }

		if (array_key_exists('scheme', $url)) {
            $url['scheme'] = strtolower($url['scheme']);
        }

		if (array_key_exists('host', $url)) {
            $url['host']   = strtolower(trim($url['host'], '.'));
        }

		if (self::$_normalize_links['host_trans']
			&& array_key_exists('host', $url)
			&& array_key_exists($url['host'], self::$_normalize_links['host_trans'])) {
			$url['host'] = self::$_normalize_links['host_trans'][ $url['host']];
		}

		$is_current_host = URLParser::is_current_host($url);
		$is_fragment_only = ($is_current_host
							 && array_key_exists('fragment', $url)
							 && ! array_key_exists('path', $url)
							 && ! array_key_exists('query', $url)) || $url === [];

		if (self::$_normalize_links['is_add_host'] && ! $is_fragment_only) {
			list($scheme, ) = explode('/', strtolower($_SERVER['SERVER_PROTOCOL']));
			$url += [
                'scheme' => $scheme,
                'host'   => strtolower($_SERVER['HTTP_HOST']),
            ];

			if (!array_key_exists('port', $url)) {
				$servbyname[$scheme] = $port = array_key_exists($scheme, $servbyname) ? $servbyname[$scheme] : getservbyname($scheme, 'tcp');
				if ($port !== false && $port !== intval($_SERVER['SERVER_PORT'])) {
                    $url['port'] = $port;
                }
			}
		}

		$is_host_only = array_key_exists('scheme', $url)
						&& array_key_exists('host', $url)
						&& ! array_key_exists('path', $url)
						&& ! array_key_exists('query', $url)
						&& ! array_key_exists('fragment', $url);

		if ($is_host_only) {
            $url['path'] = '/';
        }

		if (array_key_exists('path', $url)) {
			if ($url['path']{0} !== '/') {
                return false;
            }

			if ($is_current_host
				 && self::$_normalize_links['path_search']
				 && self::$_normalize_links['path_replace']
				 && strpos($url['path'], self::$_normalize_links['path_search']) === 0
			) {
				$url['path'] = self::$_normalize_links['path_replace'] . substr($url['path'], strlen(self::$_normalize_links['path_search']));
			}
		}

		if (!self::$_normalize_links['is_add_host'] && $is_current_host) {
            unset($url['scheme'], $url['host'], $url['port']);
        }

		if ($url && !URLParser::check($url)) {
            return false;
        }

		return $url;
	}

	private static function _url_attrs()
	{
		static $a = [];
		if (!$a) {
            $a = array_unique(explode('|', implode('|', self::$url_tags)));
        }
		return $a;
	}

	public static function replace_home_path($s, &$replace_count = 0)
	{
		if (!ReflectionTypeHint::isValid()) {
            return false;
        }

		return preg_replace_callback('
			~	(?<=	(["\'])	#1
					|	\(
				)

				#путь должен начинаться с либо с корня (только абсолютные пути), либо с ключа поддомена
				\~	\~?+	(?>		/
								|	:([a-zA-Z]+[a-zA-Z\d]*) #2 subdomain
							)

				#([^"\'\)\x00-\x20\x7f-\xff]*+)    #3
				#(?=(?(1) \\1 | \) ))

				((?(1)	[^"\'\x00-\x20\x7f-\xff]*+   (?= \\1 )
					|	[^"\'\)\x00-\x20\x7f-\xff]*+ (?= \)  )
				)) #3
			~sxSX', ['self', '_replace_home_path'], $s, -1, $replace_count);
	}

	private static function _replace_home_path(array $m)
	{
		if ($m[0]{1} === '~') return substr($m[0], 1);
		$url = '/' . $m[3];
		if (isset($m[2]) &&	array_key_exists($m[2], self::$_subdomains_map)) {
            $url = self::$_subdomains_map[$m[2]] . $url;
        }
		return self::src($url, false);
	}

	public static function optimize($s, $is_js = false, $is_css = false)
	{
		return Text_Optimize::html($s, $is_js, $is_css);
	}

	public static function entity_decode($s, $is_special_chars = false)
	{
		return UTF8::html_entity_decode($s, $is_special_chars);
	}

	public static function entity_encode($s, $is_special_chars_only = false)
	{
		return UTF8::html_entity_encode($s, $is_special_chars_only);
	}

	public static function nobr($s)
	{
		$s = str_replace('<nobr>', '<span style="white-space:nowrap">', $s);
		$s = str_replace('</nobr>', '</span>', $s);
		return $s;
	}

	public static function words_unhang($s, $chars_max = 6, $open_tag = '<nobr>', $close_tag = '</nobr>')
	{
		$s = preg_replace('~((?<=\s)\pL{1,2}+\s)?+			  #приклеиваем 1-2 буквенные слова, например предлоги и союзы: в, с, на, от, и
							[^\s;,\.!\?)%'	. "\xc2\xa0"      #&nbsp; [ ]
											. "\xc2\xbb"      #&raquo; [»]
											. "\xe2\x80\xa6"  #&hellip; […]
											. "\xc2\xae"      #&reg; [®]
											. ']*+
							.{1,' . $chars_max . '}+
							[;\.!\?)%'	. "\xc2\xbb"      #&raquo; [»]
										. "\xe2\x80\xa6"  #&hellip; […]
										. "\xc2\xae"      #&reg; [®]
										. ']*+
							$~usxSX', $open_tag . '$0' . $close_tag, $s);
		return $s;
	}

	public static function noindex($s)
	{
		$invalid_tags = $deleted_tags = [];
		$s = self::normalize_tags($s, $invalid_tags, $deleted_tags, ['noindex']);
		$s = preg_replace('~</?+noindex>
							([\x00-\x20\x7f]*+) #1
							</?+noindex>
						   ~sxiSX', '$1', $s);
		$s = str_replace('<noindex>', '<!--noindex-->', $s);
		$s = str_replace('</noindex>', '<!--/noindex-->', $s);
		return $s;
	}

}