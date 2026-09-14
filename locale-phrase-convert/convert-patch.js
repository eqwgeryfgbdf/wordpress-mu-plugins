(function (global) {
	'use strict';

	var OpenCC = global.OpenCC;
	if (!OpenCC || typeof OpenCC.Converter !== 'function' || typeof OpenCC.HTMLConverter !== 'function') {
		return;
	}

	var bundledDicts = global.LOCALE_PHRASE_DICTS || {};
	var extraDicts = global.LOCALE_PHRASE_EXTRA_DICTS || {};
	var userDicts = global.LOCALE_PHRASE_USER_DICTS || {};
	var taiwanUiFixes = [['釋出評論', '發佈評論']];
	var mainlandUiFixes = [
		['這幺', '这么'],
		['这幺', '这么'],
		['怎幺', '怎么'],
		['那幺', '那么'],
		['那幺', '那么'],
		['資料來源', '资料来源'],
		['资料来源', '资料来源']
	];

	function asPairs(value) {
		return Array.isArray(value) ? value : [];
	}

	var toTaiwanPairs = taiwanUiFixes
		.concat(asPairs(userDicts.toTaiwan))
		.concat(asPairs(extraDicts.toTaiwan))
		.concat(asPairs(bundledDicts.toTaiwan));
	var toMainlandPairs = mainlandUiFixes
		.concat(asPairs(userDicts.toMainland))
		.concat(asPairs(extraDicts.toMainland))
		.concat(asPairs(bundledDicts.toMainland));

	var customToTaiwan = typeof OpenCC.CustomConverter === 'function'
		? OpenCC.CustomConverter(toTaiwanPairs)
		: function (text) { return text; };
	var customToMainland = typeof OpenCC.CustomConverter === 'function'
		? OpenCC.CustomConverter(toMainlandPairs)
		: function (text) { return text; };

	var s2twp;
	var tw2sp;
	if (typeof OpenCC.ConverterFactory === 'function' && OpenCC.Locale && OpenCC.Locale.from && OpenCC.Locale.to) {
		s2twp = OpenCC.ConverterFactory(OpenCC.Locale.from.cn, OpenCC.Locale.to.twp);
		tw2sp = OpenCC.ConverterFactory(OpenCC.Locale.from.twp, OpenCC.Locale.to.cn);
	} else {
		s2twp = OpenCC.Converter({ from: 'cn', to: 'twp' });
		tw2sp = OpenCC.Converter({ from: 'twp', to: 'cn' });
	}

	function toTaiwan(text) {
		return customToTaiwan(s2twp(text));
	}

	function toMainland(text) {
		return customToMainland(tw2sp(text))
			.replace(/這幺/g, '这么')
			.replace(/这幺/g, '这么')
			.replace(/怎幺/g, '怎么')
			.replace(/那幺/g, '那么');
	}

	var originalConverter = OpenCC.Converter;
	OpenCC.Converter = function (options) {
		var fromLocale = options && options.from;
		var toLocale = options && options.to;
		if (fromLocale === 'cn' && (toLocale === 'tw' || toLocale === 'twp')) {
			return toTaiwan;
		}
		if ((fromLocale === 'tw' || fromLocale === 'twp') && toLocale === 'cn') {
			return toMainland;
		}
		return originalConverter.call(this, options);
	};

	function markIgnored(root) {
		if (!root || typeof root.querySelectorAll !== 'function') {
			return;
		}
		var nodes = root.querySelectorAll('pre, code, kbd, samp, .wp-block-code, .highlight');
		var index;
		for (index = 0; index < nodes.length; index += 1) {
			nodes[index].classList.add('ignore-opencc');
		}
	}

	var originalHTMLConverter = OpenCC.HTMLConverter;
	OpenCC.HTMLConverter = function (converter, root, fromLang, toLang) {
		var sourceLang = fromLang || 'zh-CN';
		var taiwanHandler = originalHTMLConverter(toTaiwan, root, sourceLang, toLang || 'zh-TW');
		var mainlandHandler = originalHTMLConverter(toMainland, root, sourceLang, 'zh-CN');
		return {
			convert: function () {
				markIgnored(root);
				taiwanHandler.convert();
			},
			restore: function () {
				markIgnored(root);
				taiwanHandler.restore();
				mainlandHandler.convert();
			}
		};
	};

	function relabelToggle() {
		var nodes = document.querySelectorAll('.sc-tc-toggle');
		var index;
		var node;
		var title;
		var nextTitle;
		for (index = 0; index < nodes.length; index += 1) {
			node = nodes[index];
			title = node.getAttribute('title') || node.getAttribute('data-bs-original-title') || '';
			nextTitle = '繁中/簡中 切換';
			if (nextTitle !== title) {
				node.setAttribute('title', nextTitle);
				node.setAttribute('data-bs-original-title', nextTitle);
			}
		}
	}

	function observeToggles() {
		if (typeof MutationObserver !== 'function') {
			return;
		}
		var observer = new MutationObserver(function () {
			relabelToggle();
		});
		var nodes = document.querySelectorAll('.sc-tc-toggle');
		var index;
		for (index = 0; index < nodes.length; index += 1) {
			observer.observe(nodes[index], {
				attributes: true,
				attributeFilter: ['title', 'data-bs-original-title']
			});
		}
	}

	function scheduleRelabel() {
		relabelToggle();
		observeToggles();
		global.setTimeout(relabelToggle, 0);
		global.setTimeout(relabelToggle, 400);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', scheduleRelabel);
	} else {
		scheduleRelabel();
	}
	document.addEventListener('click', function (event) {
		var target = event.target;
		if (!target) {
			return;
		}
		if (target.closest && target.closest('.sc-tc-toggle')) {
			scheduleRelabel();
		}
	});
})(window);
