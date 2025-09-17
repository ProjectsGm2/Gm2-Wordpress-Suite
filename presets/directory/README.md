# Directory Preset Templates

The Directory preset ships with Elementor templates that surface the custom
fields configured in `blueprint.json` so you can quickly build front-end
layouts for listings.

## Available templates

- **Listing Single** (`templates/listing-single.json`) &mdash; renders the logo,
  address, contact methods, gallery, coordinates, and opening hours for the
  current listing using GM2 widgets.
- **Listing Archive Loop** (`templates/listing-archive-loop.json`) &mdash; provides a
  Loop Item card that highlights the logo, city, street, phone number, and price
  level when used inside Elementor's Loop Grid or Posts widget.

## Import instructions

1. Visit **Elementor → Templates** in the WordPress dashboard.
2. Click **Import Templates** and upload one of the JSON exports from the
   `presets/directory/templates/` folder.
3. For the single template, open **Theme Builder → Single** and assign the
   imported layout to the **Listing** post type.
4. For the archive loop card, open **Theme Builder → Loop**, import the loop
   template, and select it when configuring Loop Grid or Archive Posts widgets
   on your archive pages.

After importing you can duplicate and customise the templates while retaining
all of the preset field bindings.
