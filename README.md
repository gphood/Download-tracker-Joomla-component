# Download Tracker for Joomla

Reusable Joomla 5/6 component for tracking and securely delivering extension and product downloads.

## Features

- Manage multiple products or extensions.
- Manage downloadable items with aliases, editions, versions, external target URLs, or private files.
- Protect private downloads with limited tokens or persistent Joomla update keys.
- Publish Joomla-compatible update XML from the current Download Item version and package checksum.
- Replace a customer update key while revoking the old key.
- Log authorised and denied requests without storing the full secret key.
- Administrator log view and CSV export.
- SEF-friendly `/download/{alias}` frontend route when a matching Joomla menu item exists.
- Joomla MVC structure with administrator and site applications.

## Install

Install the public component release package `com_downloadtracker-0.1.30.zip` through Joomla Administrator:

```text
System -> Install -> Extensions
```

## Build

The root `downloadtracker.xml` is the source manifest. The administrator copy is generated from it during builds:

```text
./build.sh
```

Do not edit `administrator/downloadtracker.xml` directly.

## Basic Setup

1. Go to `Components -> Download Tracker -> Products`.
2. Create and publish a product.
3. Go to `Components -> Download Tracker -> Download Items`.
4. Create and publish a download item.
5. Set the item alias and target URL.

## Test URL

The non-SEF fallback URL remains available:

```text
/index.php?option=com_downloadtracker&task=download.redirect&alias=your-download-alias
```

Example:

```text
/index.php?option=com_downloadtracker&task=download.redirect&alias=decision-tree-free-latest
```

For the preferred clean URL:

```text
/download/decision-tree-free-latest
```

create a Joomla menu item with these settings:

- Create or use a hidden menu.
- Menu item type: `Download Tracker -> Download Redirect`
- Menu title: `Download`
- Alias: `download`
- Status: `Published`
- Menu: the hidden menu

No child menu items are needed. The component router is registered through `administrator/services/provider.php` using Joomla's `RouterFactory`; the component extension implements `RouterServiceInterface`, and the site router lives at `site/src/Service/Router.php`.

When requested, the component looks up the published item, confirms the parent product is published, records a log entry, and either streams a protected private file or sends a 302 redirect to an external target.

## Protected Joomla updates

For a private Joomla extension package:

1. Set the Download Item source to `Private local file` and require a download token.
2. In the Download Item's `Joomla updates` section, enable the update feed.
3. Add any product-specific prerequisites to `Customer installation instructions`; these are appended to purchase and update-key emails.
4. Enter the installed extension element, type, supported Joomla expression, PHP minimum, and the ZIP's SHA-256 checksum.
5. Give the package manifest an update server URL in this form:

```text
/index.php?option=com_downloadtracker&task=download.update&alias=your-download-alias&format=raw
```

6. Add `<dlid prefix="token=" suffix="" />` to the package manifest.

Keys created with the persistent update purpose, whether by Stripe or manually, have no expiry or usage limit. The purchaser receives the key by email and enters it once in the installed update site's `Download Key` field. The public XML contains release metadata only; downloading the ZIP still requires a valid key.

## Database Tables

- `#__downloadtracker_products`
- `#__downloadtracker_items`
- `#__downloadtracker_tokens`
- `#__downloadtracker_logs`

## Known Limitations

The `/download/{alias}` route needs a published Joomla menu item with the alias `download`. Without that menu item, use the non-SEF fallback URL shown above.

Subscriptions, GitHub API syncing, and charts are outside this component's scope. Ecommerce integrations can issue tokens through `DownloadFulfilmentService`.
