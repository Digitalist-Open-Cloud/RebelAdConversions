# Rebel Ad Conversions

[![PHPCS check](https://github.com/Digitalist-Open-Cloud/RebelAdConversions/actions/workflows/phpcs.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/RebelAdConversions/actions/workflows/phpcs.yaml)
[![Semgrep OSS scan](https://github.com/Digitalist-Open-Cloud/RebelAdConversions/actions/workflows/semgrep.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/RebelAdConversions/actions/workflows/semgrep.yaml)
[![Test plugin with Matomo](https://github.com/Digitalist-Open-Cloud/RebelAdConversions/actions/workflows/matomo.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/RebelAdConversions/actions/workflows/matomo.yaml)
[![PHPStan check](https://github.com/Digitalist-Open-Cloud/RebelAdConversions/actions/workflows/phpstan.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/RebelAdConversions/actions/workflows/phpstan.yaml)

A Matomo plugin that sends the conversions Matomo measures back to Google Ads and Microsoft
Advertising, so that the ad platforms know which clicks led to a goal or an order.

- It stores the click ID an ad platform adds to the address of the landing page (`gclid`,
  `msclkid`), only for visitors who agreed to marketing.
- It makes a file of conversions for each platform: click ID, conversion name, time, value and
  currency.

## Install

Activate the plugin. It adds itself to the JavaScript tracker, `matomo.js`. If Matomo can not
write `matomo.js`, run:

```sh
./console custom-matomo-js:update
```

Auto-tagging must be on in the ad account, so that the click ID is in the address of the landing
page.

## Choose what is exported

Go to **Administration > Websites > Manage** and edit the website. Under "Conversions to send to
ad platforms", add a row for each goal: the goal, and the name its conversion has on the ad
platform, exactly as it is written there. "Ecommerce orders" exports the orders of the website.

Nothing is stored for a website without such a row.

## Consent

A click ID is personal data, and it is only stored when the website says that the visitor agreed
to marketing. Let the consent tool make this call on every page where the visitor has agreed, and
right after they agree:

```js
_paq.push(['RebelAdConversions::setConsentGiven']);
```

The consent of Matomo itself (`setConsentGiven`, `rememberConsentGiven`) is about analytics and
does not count here.

The click ID is read from the address of the page and kept in memory until the call is made. A
visitor who agrees on a later page than the landing page is not matched to the ad.

## The files

**Administration > Websites > Ad conversions** has, for each platform, the file to download and
an address the platform can fetch on a schedule. You need admin access to the website.

| Platform | Click ID column | First line |
|---|---|---|
| Google Ads | `Google Click ID` | `Parameters:TimeZone=+0000` |
| Microsoft Advertising | `Microsoft Click ID` | the column names |

The other columns are `Conversion Name`, `Conversion Time`, `Conversion Value` and
`Conversion Currency`. Times are in UTC. The value is the revenue of the goal or the order, in the
currency of the website.

A file holds the conversions of the last 30 days. Add `&days=<number>`, up to 90, for another
number of days. The same conversion can be uploaded again: the ad platforms count it once.

A conversion is exported with the last ad click of its visitor on that platform, when the click
is at most 90 days older than the conversion.

### Scheduled import

The address for a scheduled import holds a secret of the website and needs no login. The secret
can also be given as the password of HTTP authentication, with any user name, and left out of the
address. Anyone who has the address can read the file, so treat it as a password. "Replace the
secret" on the page makes the old addresses stop working.

## Privacy

- Click IDs are stored in their own table with the visit. Deleting a visit, by the data retention
  of Matomo or by the GDPR tools, deletes its click.
- Deleting a website deletes its clicks and its secret.
- The plugin sends nothing to an ad platform by itself. Data leaves Matomo when a file is
  downloaded or fetched.

## Development

```sh
./console tests:run RebelAdConversions
deno test --allow-read tests/js
```
