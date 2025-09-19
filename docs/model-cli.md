# Model WP-CLI commands

Manage custom post types, taxonomies, and field groups from the command line. All commands share the `wp gm2` prefix.

## Custom post types (`wp gm2 cpt`)

Dedicated shortcuts exist for creating, removing, and listing CPT definitions stored in the `gm2_models` option. Legacy `wp gm2 model cpt ...` subcommands continue to function, but the streamlined syntax below is preferred:

```bash
wp gm2 cpt add <slug> [--args=<json>]
wp gm2 model update cpt <slug> [--args=<json>] [--version=<version>]
wp gm2 cpt remove <slug>
wp gm2 cpt list [<slug>]
```

The `list` subcommand prints a table including the CPT slug, label, version, and any attached taxonomies.

## Taxonomies (`wp gm2 tax`)

Taxonomies can be managed directly with the `gm2 tax` namespace:

```bash
wp gm2 tax add <cpt> <slug> [--args=<json>]
wp gm2 model update taxonomy <cpt> <slug> [--args=<json>]
wp gm2 tax remove <cpt> <slug>
wp gm2 tax list [<cpt>]
```

`list` outputs a table with each taxonomy slug, its associated CPT, and label.

## Field groups

Field groups continue to use the nested `gm2 model field` subcommands:

```bash
wp gm2 model create field <cpt> <key> [--args=<json>]
wp gm2 model update field <cpt> <key> [--args=<json>]
wp gm2 model delete field <cpt> <key>
```

### Exporting and importing field groups

Use the dedicated `gm2 fields` command when working solely with field group data:

```bash
wp gm2 fields export <file> [--format=<json|yaml>] [--slug=<slug>] [--slugs=<list>]
wp gm2 fields import <file> [--format=<json|yaml>] [--replace]
```

`--slug` may be provided multiple times, and `--slugs` accepts a comma-separated list. Imports merge into existing groups unless `--replace` is supplied.

## Exporting and importing models

```bash
wp gm2 model export <file> [--format=<json|yaml>] [--field-groups]
wp gm2 model import <file> [--format=<json|yaml>] [--field-groups] [--replace]
```

The `--field-groups` flag retains its previous behaviour for backwards compatibility but `wp gm2 fields` provides a clearer entry point.

## Migrations

Run pending migrations for all models:

```bash
wp gm2 model migrate
```

## Blueprints

The `gm2 blueprint` command provides a shortcut for working with JSON blueprints that describe post types, taxonomies and field groups.

```bash
wp gm2 blueprint export blueprint.json
wp gm2 blueprint import directory.json events.json
```

Imports are validated against `presets/schema.json` and sample blueprints are available in `assets/blueprints/samples/`. Bundled presets ship under `presets/{preset}/blueprint.json`.

## GraphQL naming

Models created via the CLI now expose their data to WPGraphQL automatically. Post types and taxonomies are registered with `show_in_graphql` enabled and receive PascalCase single/plural names derived from their labels. Override those names when necessary with the `gm2/graphql/post_type_single_name`, `gm2/graphql/post_type_plural_name`, `gm2/graphql/taxonomy_single_name`, and `gm2/graphql/taxonomy_plural_name` filters. Field keys become camelCase GraphQL fields, and the `gm2/graphql/field_name` filter allows projects to customise them before registration.

## Seeding data

```bash
wp gm2 model seed
```
