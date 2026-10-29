# Unglomar

A userscript that gets you past the "You've been Glomar'd" page on dev and staging sites that use the default `secret` bypass. It runs in Chrome, Firefox, and Edge through [Tampermonkey](https://www.tampermonkey.net/).

## Install

1. Install Tampermonkey. In Chrome, go to `chrome://extensions/`, click Details under Tampermonkey, and turn on Allow User Scripts and Allow in Incognito.
2. Click the button below, then click Install on the page Tampermonkey opens. To update later, click it again.

<p>
	<a href="https://raw.githubusercontent.com/moderntribe/tribe-glomar/main/tools/unglomar/unglomar.user.js">
		<img src="https://img.shields.io/badge/Install_Unglomar-Tampermonkey-2563eb?style=for-the-badge&logo=tampermonkey&logoColor=white" alt="Install Unglomar userscript">
	</a>
</p>

If the button doesn't open the installer, open the [raw script](https://raw.githubusercontent.com/moderntribe/tribe-glomar/main/tools/unglomar/unglomar.user.js) directly.

## How it works

Click a link to a glomar'd site from Jira, Slack, or anywhere else in the browser, and Unglomar reloads that exact link, including its query string and `#fragment`, with `?secret=1` added. The plugin sets its bypass cookies on that request, and Unglomar drops the param from the address bar. It retries once per tab, so it never loops.

Pasted URLs and links opened from desktop apps can't be recovered. On most sites, Unglomar opens the homepage instead, and pasting the URL again works. Demo sites on `*.moderntribe.qa` use the "Login Page" glomar action and send you to the login page instead, but Unglomar still sets the cookies. Click the link to the site under the login form, or paste your URL again.

Once a site is unlocked, the cookies usually keep you through. Some hosts, like Pantheon, strip the `secret` cookie, so every page bounces to the glomar page and Unglomar retries it with `?secret=1`.

## Sites with a different secret

The plugin's secret is the name of a query param, and the value can be anything non-empty. If you stay stuck on the glomar or login page, the site uses a different name. Ask for it, then add it to any URL once, e.g. `?letmein=1`. The plugin sets its own 30-day cookie for it.

## Notes

- Incognito drops cookies when the last incognito window closes, so each new session starts locked again.
- Anything in `?secret=1` shows up in browser history and server logs. Keep this to dev and staging.

## Updating

Bump `@version` in the script with every change. Tampermonkey only offers the update to installed copies when the version goes up.
