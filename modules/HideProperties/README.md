# Hide Properties (for Omeka S)

## An Omeka S module allowing administrators to choose properties to hide from public view

This is more or less an Omeka S version of my Hide Elements plugin for Omeka Classic.

This module currently only affects places where a view or theme requests to show *all*
the metadata for a property, i.e., on the "show" page for an item, item set, or media
resource. Theme calls to explicitly print specified values, API access, and other areas
where values might appear will continue to work as usual.
