// ==UserScript==
// @name         Unglomar
// @namespace    https://moderntribeagency.com/unglomar
// @version      2.5.0
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
 * wordpress_logged_in_* cookie before they pass the request to PHP. Some
 * hosts (e.g. Pantheon) strip unknown cookies like `secret` at the edge, so
 * only the query param works there.
 *
 * This script:
 *   1. Remembers the last link you clicked or right-clicked (one shared
 *      value), so a tab that lands on the block page knows where you were
 *      headed.
 *   2. On /glomar, retries that link (or the homepage) with ?secret=1 once
 *      per tab, then drops the param from the address bar. The plugin sets
 *      its own cookies on that request, using the site's COOKIE_DOMAIN.
 *   3. On *.moderntribe.qa only, where demo sites use the "Login Page"
 *      action, a bare wp-login.php retries the same way, but only if you just
 *      clicked a link to this site, since it can also be a real login.
 *      Otherwise it sets both cookies itself, so pasting the URL again works.
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
	 * Sets both cookies needed to get past Glomar and the host's page cache.
	 */
	function unlock() {
		setCookie(SECRET);
		setCookie(CACHE_COOKIE);
	}

	/**
	 * Remembers a clicked link, for the block page to retry.
	 */
	function rememberClick(e) {
		const a = e.target.closest?.('a[href]');
		if (!(a instanceof HTMLAnchorElement)) return;
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
		// A bounce to the login page without a click may be a real login page, so stay put.
		const target = recentClick() || (onGlomar && location.origin + '/');
		if (!target) {
			unlock();
			return;
		}
		// Already retried in this tab and got bounced again: wrong secret, so stay put.
		if (sessionStorage.getItem(TRIED_KEY)) return;
		sessionStorage.setItem(TRIED_KEY, '1');
		// Use each click once, so a later visit to the login page stays put.
		GM_setValue('lastClick', null);
		const url = new URL(target);
		url.searchParams.set(SECRET, '1');
		location.replace(url.href);
		return;
	}

	if (sessionStorage.getItem(TRIED_KEY)) {
		sessionStorage.removeItem(TRIED_KEY);
		const url = new URL(location.href);
		url.searchParams.delete(SECRET);
		history.replaceState(history.state, '', url.href);
	}
})();
