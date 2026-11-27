# List of available commands

## Create a template

```bash
bin/console lle:pdf-generator:create-model
```

Asks for the code, the label, the type and the resource of a new template. Does not work with
[your own entity](models.md#your-own-entity) if it has other required fields: use the
[admin screen](admin-screen.md) instead.

## Start the history of the template files

```bash
bin/console lle:pdf-generator:init-versions
```

Creates the first version of the existing templates (see [History of the template files](versions.md#existing-templates)).
