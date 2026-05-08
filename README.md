# Download Tracker for Joomla

Reusable Joomla 5/6 component for tracking extension and product download requests, then redirecting visitors to configured external release assets such as GitHub release files.

## Features

- Manage multiple products or extensions.
- Manage downloadable items with aliases, editions, versions, and external target URLs.
- Log each download request before redirecting.
- Read-only administrator log view.
- SEF-friendly `/download/{alias}` frontend route when a matching Joomla menu item exists.
- Joomla MVC structure with administrator and site applications.

## Install

Install `com_downloadtracker-0.1.10.zip` through Joomla Administrator:

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

When requested, the component looks up the published item, confirms the parent product is published, records a log entry, and sends a 302 redirect to the configured target URL.

## Database Tables

- `#__downloadtracker_products`
- `#__downloadtracker_items`
- `#__downloadtracker_logs`

## Known Limitations

The `/download/{alias}` route needs a published Joomla menu item with the alias `download`. Without that menu item, use the non-SEF fallback URL shown above.

Update-server integration, ecommerce, licence keys, subscriptions, GitHub API syncing, charts, and bot filtering rules are intentionally out of scope for this build.
