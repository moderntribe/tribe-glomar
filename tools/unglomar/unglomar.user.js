// ==UserScript==
// @name         Unglomar
// @namespace    https://moderntribeagency.com/unglomar
// @version      2.3.0
// @description  Gets you past Tribe Glomar on dev and staging sites by setting the bypass cookies and retrying the link you clicked.
// @author       Brian Alexander
// @match        *://*/*
// @noframes
// @run-at       document-start
// @grant        GM_setValue
// @grant        GM_getValue
// ==/UserScript==

/*
 * Tribe Glomar 303-redirects blocked requests to <home>/glomar and drops the
 * original URL. Sites using the "Login Page" action then 302 on to a bare
 * wp-login.php. The plugin lets a request through when it carries a non-empty
 * `secret` query param or cookie. Managed hosts' page caches also need a
 * wordpress_logged_in_* cookie before they pass the request to PHP.
 *
 * This script:
 *   1. Remembers the last cross-site link you clicked or right-clicked (one
 *      shared value), so a new tab that lands on the block page knows where
 *      you were headed.
 *   2. On /glomar, sets both cookies and retries that link (or the homepage)
 *      once per tab.
 *   3. On *.moderntribe.qa only, where demo sites use the "Login Page"
 *      action, a bare wp-login.php sets both cookies. It only retries if you
 *      just clicked a link to this site, since it can also be a real login.
 *   4. On sites that already have the `secret` cookie, renews both cookies.
 *      The plugin resends the cache cookie with a 10-minute expiry on every
 *      uncached page, so without this it would lapse after 10 idle minutes.
 */

(function () {
	'use strict';

	const SECRET = 'secret';
	const CACHE_COOKIE = 'wordpress_logged_in_glomar';
	const MAX_AGE = 60 * 60 * 24 * 365;
	const CLICK_TTL_MS = 30 * 1000;
	const TRIED_KEY = 'unglomar:tried';

	const host = location.hostname;

	/**
	 * Sets a host-only bypass cookie with a value of 1.
	 */
	function setCookie(name) {
		document.cookie = `${name}=1; path=/; max-age=${MAX_AGE}; SameSite=Lax`;
	}

	/**
	 * Whether this site already has a cookie by the given name.
	 */
	function hasCookie(name) {
		return document.cookie.split('; ').some((c) => c.startsWith(name + '='));
	}

	/**
	 * Sets both cookies needed to get past Glomar and the host's page cache.
	 */
	function unlock() {
		setCookie(SECRET);
		setCookie(CACHE_COOKIE);
	}

	/**
	 * Remembers a clicked link to another site, for a new tab to pick up.
	 */
	function rememberClick(e) {
		const a = e.target.closest?.('a[href]');
		if (!(a instanceof HTMLAnchorElement) || a.hostname === host) return;
		GM_setValue('lastClick', { url: a.href, time: Date.now() });
	}

	/**
	 * The URL of a recent click to this site, or null.
	 */
	function recentClick() {
		const click = GM_getValue('lastClick');
		if (click && Date.now() - click.time < CLICK_TTL_MS && new URL(click.url).hostname === host) {
			return click.url;
		}
		return null;
	}

	document.addEventListener('click', rememberClick, true);
	document.addEventListener('auxclick', rememberClick, true);
	document.addEventListener('contextmenu', rememberClick, true);

	const onGlomar = /\/glomar\/?$/i.test(location.pathname);
	const onLoginBounce = host.endsWith('.moderntribe.qa') &&
		/\/wp-login\.php$/.test(location.pathname) &&
		!location.search;

	if (onGlomar || onLoginBounce) {
		unlock();
		// A bounce to the login page without a click may be a real login page, so stay put.
		const target = recentClick() || (onGlomar && location.origin + '/');
		// Already retried in this tab and got bounced again: wrong secret, so stay put.
		if (!target || sessionStorage.getItem(TRIED_KEY)) return;
		sessionStorage.setItem(TRIED_KEY, '1');
		// Use each click once, so a later visit to the login page stays put.
		GM_setValue('lastClick', null);
		location.replace(target);
		return;
	}

	sessionStorage.removeItem(TRIED_KEY);

	if (hasCookie(SECRET)) unlock();
})();
