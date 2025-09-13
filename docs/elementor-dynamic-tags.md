# Elementor Dynamic Tags

Gm2 adds a **GM2 Field** dynamic tag for Elementor that exposes values from your
registered field groups.

1. In any Elementor control that supports dynamic data, choose **GM2 Field**
   from the dynamic tags list under the "GM2" group.
2. Select a field from the dropdown. Nested fields use dot notation, e.g.
   `address.city` for a group field or `slides.0.image` for the first item in a
   repeater.
3. Optionally set a fallback value that will be used when the field is empty.

Field types are mapped to the appropriate Elementor categories (text, media,
URL, gallery) so they appear only where supported. Complex field structures like
repeaters and groups are flattened automatically allowing direct access to their
sub‑fields using the dot notation shown above.

This makes GM2 field data available throughout Elementor without additional
custom code.
