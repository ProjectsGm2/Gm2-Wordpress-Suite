# Model WP-CLI commands

Manage custom post types, taxonomies, and field groups from the command line.
All commands are run with the `wp gm2 model` prefix.

## Creating models

### Custom post type

```bash
wp gm2 model create cpt <slug> [--args=<json>]
```

### Taxonomy

```bash
wp gm2 model create taxonomy <cpt> <slug> [--args=<json>]
```

### Field group

```bash
wp gm2 model create field <cpt> <key> [--args=<json>]
```

## Updating models

```bash
wp gm2 model update cpt <slug> [--args=<json>] [--version=<version>]
wp gm2 model update taxonomy <cpt> <slug> [--args=<json>]
wp gm2 model update field <cpt> <key> [--args=<json>]
```

`modify` is an alias for `update`.

## Deleting models

```bash
wp gm2 model delete cpt <slug>
wp gm2 model delete taxonomy <cpt> <slug>
wp gm2 model delete field <cpt> <key>
```

## Migrations

Run pending migrations for all models:

```bash
wp gm2 model migrate
```

## Exporting and importing

```bash
wp gm2 model export <file> [--format=<json|yaml>]
wp gm2 model import <file> [--format=<json|yaml>]
```

## Blueprints

The `gm2 blueprint` command provides a shortcut for working with JSON blueprints that describe post types, taxonomies and field groups.

```bash
wp gm2 blueprint export blueprint.json
wp gm2 blueprint import directory.json events.json
```

Imports are validated against `assets/blueprints/schema.json` and sample blueprints are available in `assets/blueprints/samples/`.

## Seeding data

```bash
wp gm2 model seed
```
